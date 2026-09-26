<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Customer password policy (audit: "Password policy is length ≥ 8 and nothing else").
 *
 * Rules — deliberately simple and NIST-aligned (length first, no forced symbols):
 *   - 8 to 128 characters
 *   - at least one letter and one digit
 *   - not one of the most common passwords
 *   - must not contain the account's email local-part or name
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    /** Human-readable requirements, shown under password fields. */
    public const HINT = 'At least 8 characters with a letter and a number. Avoid common passwords and your own name or email.';

    private const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789', '1234567890',
        'qwerty123', 'qwertyuiop', 'iloveyou', 'admin123', 'welcome1', 'letmein1', 'abc12345',
        'sunshine1', 'football1', 'princess1', 'monkey123', 'dragon123', 'baseball1', 'sksl1234',
        'sportslab', 'recovery1', 'sarakinetic', 'kinetic123',
    ];

    /**
     * @return string|null error message, or null when the password is acceptable
     */
    public static function validate(string $password, string $email = '', string $name = ''): ?string
    {
        $len = strlen($password);
        if ($len < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters long.';
        }
        if ($len > self::MAX_LENGTH) {
            return 'Password must be no longer than ' . self::MAX_LENGTH . ' characters.';
        }
        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            return 'Password must include at least one letter and one number.';
        }

        $lower = mb_strtolower($password);
        $stripped = preg_replace('/[^a-z0-9]/', '', $lower) ?? $lower;
        if (in_array($lower, self::COMMON, true) || in_array($stripped, self::COMMON, true)) {
            return 'That password is too common. Please choose something less guessable.';
        }

        $local = mb_strtolower(trim((string) strstr($email, '@', true) ?: $email));
        if (mb_strlen($local) >= 4 && str_contains($lower, $local)) {
            return 'Password must not contain your email address.';
        }
        foreach (preg_split('/\s+/', mb_strtolower(trim($name))) ?: [] as $part) {
            if (mb_strlen($part) >= 4 && str_contains($lower, $part)) {
                return 'Password must not contain your name.';
            }
        }

        return null;
    }
}
