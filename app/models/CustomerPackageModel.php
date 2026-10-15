<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Customer Package Model
 *
 * Manages customer package purchases, credits tracking, expiry, and atomic consumption.
 */
class CustomerPackageModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Get all package purchases for a customer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByUserId(int $userId, bool $activeOnly = false): array
    {
        $sql = "SELECT cp.*, p.name as current_package_name, p.slug as package_slug
                FROM customer_packages cp
                JOIN packages p ON cp.package_id = p.id
                WHERE cp.user_id = :user_id";
        if ($activeOnly) {
            $sql .= " AND cp.status = 'active' AND cp.expires_at > NOW() AND cp.payment_status = 'paid'";
        }
        $sql .= " ORDER BY cp.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $packages = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($packages as &$pkg) {
            $pkg['credits'] = $this->getCredits((int) $pkg['id']);
        }
        return $packages;
    }

    /**
     * Find customer package by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile
             FROM customer_packages cp
             JOIN users u ON cp.user_id = u.id
             WHERE cp.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($pkg) {
            $pkg['credits'] = $this->getCredits((int) $pkg['id']);
        }
        return $pkg;
    }

    /**
     * Find active paid customer package for a user.
     */
    public function findActiveForUser(int $userId, int $customerPackageId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, p.name as current_package_name, p.slug as package_slug
             FROM customer_packages cp
             JOIN packages p ON cp.package_id = p.id
             WHERE cp.id = :id
               AND cp.user_id = :user_id
               AND cp.status = 'active'
               AND cp.expires_at > NOW()
               AND cp.payment_status = 'paid'
             LIMIT 1"
        );
        $stmt->execute(['id' => $customerPackageId, 'user_id' => $userId]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($pkg) {
            $pkg['credits'] = $this->getCredits((int) $pkg['id']);
        }
        return $pkg;
    }

    /**
     * A customer's most recent unpaid purchase of a package, if it is still fresh.
     */
    public function findRecentPending(int $userId, int $packageId, int $withinMinutes): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM customer_packages
             WHERE user_id = :user_id AND package_id = :package_id
               AND payment_status = 'pending' AND status = 'active'
               AND created_at > (NOW() - INTERVAL :mins MINUTE)
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('package_id', $packageId, \PDO::PARAM_INT);
        $stmt->bindValue('mins', $withinMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id ? $this->findById((int) $id) : null;
    }

    /**
     * Close checkouts that were never paid. The purchase is cancelled; its payment row
     * stays pending, and a late webhook for such an order is still honoured by the
     * settlement path, which only looks at payment_status, not at this housekeeping.
     *
     * @return int purchases expired
     */
    public function expireStalePending(int $olderThanMinutes = 1440): int
    {
        // Online checkouts: unpaid for a day. Offline requests keep their own payment deadline.
        $stmt = $this->db->prepare(
            "UPDATE customer_packages
             SET payment_status = 'failed', status = 'cancelled'
             WHERE payment_status = 'pending' AND status = 'active' AND payment_channel = 'online'
               AND created_at < (NOW() - INTERVAL :mins MINUTE)"
        );
        $stmt->bindValue('mins', $olderThanMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();

        $off = $this->db->prepare(
            "UPDATE customer_packages
             SET payment_status = 'failed', status = 'cancelled'
             WHERE payment_status = 'pending' AND status = 'active' AND payment_channel = 'offline'
               AND payment_due_at IS NOT NULL AND payment_due_at < NOW()"
        );
        $off->execute();
        return $count + $off->rowCount();
    }

    /**
     * An offline request for this package that is still waiting for payment (so a second click
     * returns the same request instead of creating another).
     */
    public function findPendingOffline(int $userId, int $packageId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM customer_packages
             WHERE user_id = ? AND package_id = ? AND payment_channel = 'offline'
               AND payment_status = 'pending' AND status = 'active' AND payment_due_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $packageId]);
        $id = $stmt->fetchColumn();
        return $id ? $this->findById((int) $id) : null;
    }

    /**
     * Find customer package by purchase reference.
     */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile
             FROM customer_packages cp
             JOIN users u ON cp.user_id = u.id
             WHERE cp.purchase_reference = ? LIMIT 1"
        );
        $stmt->execute([$reference]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($pkg) {
            $pkg['credits'] = $this->getCredits((int) $pkg['id']);
        }
        return $pkg;
    }

    /**
     * Get credits for a customer package.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCredits(int $customerPackageId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cpc.*, cpc.remaining_quantity as remaining_credits, cpc.allocated_quantity as total_credits,
                    s.name as service_name, s.slug as service_slug,
                    COALESCE(cpc.duration_minutes, s.duration_minutes) AS duration_minutes, s.image
             FROM customer_package_credits cpc
             JOIN services s ON cpc.service_id = s.id
             WHERE cpc.customer_package_id = ?
             ORDER BY s.name ASC"
        );
        $stmt->execute([$customerPackageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find credit by credit ID.
     */
    public function getCreditById(int $creditId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cpc.*, cp.user_id, cp.status as package_status, cp.expires_at, cp.payment_status,
                    s.name as service_name, s.slug as service_slug
             FROM customer_package_credits cpc
             JOIN customer_packages cp ON cpc.customer_package_id = cp.id
             JOIN services s ON cpc.service_id = s.id
             WHERE cpc.id = ? LIMIT 1"
        );
        $stmt->execute([$creditId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create customer package purchase record with initial credit allocation.
     *
     * @param array<string, mixed> $data
     * @param array<int, array{service_id: int, quantity: int}> $items
     */
    public function createPurchase(array $data, array $items): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO customer_packages (
                    user_id, package_id, purchase_reference, package_name_snapshot,
                    validity_days_snapshot, base_amount, gst_percent, gst_amount,
                    total_amount, payment_status, expires_at, status
                 ) VALUES (
                    :user_id, :package_id, :ref, :name, :validity,
                    :base, :gst_p, :gst_a, :total, :pay_status, :expires, :status
                 )"
            );
            $stmt->execute([
                'user_id'    => (int) $data['user_id'],
                'package_id' => (int) $data['package_id'],
                'ref'        => $data['purchase_reference'],
                'name'       => $data['package_name_snapshot'],
                'validity'   => (int) $data['validity_days_snapshot'],
                'base'       => (float) $data['base_amount'],
                'gst_p'      => (float) ($data['gst_percent'] ?? 18.0),
                'gst_a'      => (float) $data['gst_amount'],
                'total'      => (float) $data['total_amount'],
                'pay_status' => $data['payment_status'] ?? 'pending',
                'expires'    => $data['expires_at'],
                'status'     => $data['status'] ?? 'active',
            ]);
            $custPkgId = (int) $this->db->lastInsertId();

            $creditStmt = $this->db->prepare(
                "INSERT INTO customer_package_credits (
                    customer_package_id, service_id, allocated_quantity, used_quantity, remaining_quantity
                 ) VALUES (?, ?, ?, 0, ?)"
            );
            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                if ($qty > 0) {
                    $creditStmt->execute([$custPkgId, (int) $item['service_id'], $qty, $qty]);
                }
            }

            $this->db->commit();
            return $custPkgId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark customer package as paid upon successful payment verification.
     */
    public function markPaid(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE customer_packages
             SET payment_status = 'paid', purchased_at = NOW()
             WHERE id = ? AND payment_status != 'paid'"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Atomically consume one credit of a service from a customer package.
     * Row-locks the credit so two requests cannot spend the last credit twice.
     * The caller must already have verified that the package belongs to the user;
     * there is deliberately one argument order and no fallback lookup, so ids from
     * different tables can never be confused.
     *
     * @return array{success: bool, credit_id?: int, message: string}
     */
    public function consumeCredit(int $customerPackageId, int $serviceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cpc.id, cpc.remaining_quantity, cp.status, cp.expires_at, cp.payment_status
             FROM customer_package_credits cpc
             JOIN customer_packages cp ON cpc.customer_package_id = cp.id
             WHERE cpc.customer_package_id = :pkg_id
               AND cpc.service_id = :service_id
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['pkg_id' => $customerPackageId, 'service_id' => $serviceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'No package credit found for this modality.'];
        }

        if (($row['payment_status'] ?? '') !== 'paid') {
            return ['success' => false, 'message' => 'Package payment has not been completed.'];
        }

        if (($row['status'] ?? '') !== 'active' || strtotime((string) $row['expires_at']) <= time()) {
            return ['success' => false, 'message' => 'Package has expired or is no longer active.'];
        }

        if ((int) $row['remaining_quantity'] <= 0) {
            return ['success' => false, 'message' => 'No remaining credits left for this modality in your package.'];
        }

        $creditId = (int) $row['id'];
        $upd = $this->db->prepare(
            "UPDATE customer_package_credits
             SET remaining_quantity = remaining_quantity - 1,
                 used_quantity = used_quantity + 1
             WHERE id = :credit_id AND remaining_quantity > 0"
        );
        $upd->execute(['credit_id' => $creditId]);

        if ($upd->rowCount() === 0) {
            return ['success' => false, 'message' => 'Unable to deduct credit. Insufficient balance.'];
        }

        return [
            'success'   => true,
            'credit_id' => $creditId,
            'message'   => 'Credit successfully consumed.',
        ];
    }

    /**
     * Idempotently restore a package credit (called when Admin marks No Show).
     * Supports:
     *   restoreCredit(int $creditId)
     *   restoreCredit(int $customerPackageId, int $serviceId, int $quantity = 1)
     */
    public function restoreCredit(int $firstArg, ?int $serviceId = null, int $quantity = 1): array
    {
        if ($serviceId !== null) {
            $stmt = $this->db->prepare(
                "UPDATE customer_package_credits
                 SET remaining_quantity = remaining_quantity + :qty1,
                     used_quantity = GREATEST(0, used_quantity - :qty2)
                 WHERE customer_package_id = :pkg_id AND service_id = :srv_id AND used_quantity > 0"
            );
            $stmt->execute([
                'qty1'   => $quantity,
                'qty2'   => $quantity,
                'pkg_id' => $firstArg,
                'srv_id' => $serviceId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "UPDATE customer_package_credits
                 SET remaining_quantity = remaining_quantity + :qty1,
                     used_quantity = GREATEST(0, used_quantity - :qty2)
                 WHERE id = :credit_id AND used_quantity > 0"
            );
            $stmt->execute([
                'qty1'      => $quantity,
                'qty2'      => $quantity,
                'credit_id' => $firstArg,
            ]);
        }

        $success = $stmt->rowCount() > 0;
        return [
            'success'  => $success,
            'restored' => $success,
        ];
    }

    /**
     * Get all package purchases for Admin dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPurchases(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile
             FROM customer_packages cp
             JOIN users u ON cp.user_id = u.id
             ORDER BY cp.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $purchases = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($purchases as &$p) {
            $p['credits'] = $this->getCredits((int) $p['id']);
        }
        return $purchases;
    }
}
