<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class UserRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (function_exists('db_pdo')) {
            $this->pdo = db_pdo();
            return $this->pdo;
        }

        throw new RuntimeException('Database connection (PDO) is not configured or available.');
    }

    /**
     * Find a user by username.
     */
    public function findByUsername(string $username): ?array
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');
        $hasMustChangeCol = $this->hasColumn('users', 'must_change_password');

        $cols = ['user_id', 'username', 'password', 'user_type', 'full_name', 'email', 'contact_number', 'org_name'];
        if ($hasUidCol) {
            $cols[] = 'uid';
        }
        if ($hasMustChangeCol) {
            $cols[] = 'must_change_password';
        }

        $colSql = implode(', ', $cols);
        $stmt = $pdo->prepare("SELECT {$colSql} FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Find a user by surrogate integer UID (or user_id fallback).
     */
    public function findByUid(int $uid): ?array
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');

        if ($hasUidCol) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE uid = :uid LIMIT 1');
            $stmt->execute([':uid' => $uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        // Fallback search by string user_id
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => (string) $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Find a user by string user_id.
     */
    public function findByUserId(string $userId): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * List all users with their assigned lab IDs and names.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllWithLabs(): array
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');
        $hasLabIncharges = $this->hasTable('lab_incharges');

        $usersStmt = $pdo->query('SELECT * FROM users ORDER BY user_type ASC, username ASC');
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['labs'] = [];
            $uid = isset($user['uid']) ? (int) $user['uid'] : null;
            $username = (string) $user['username'];

            if ($hasLabIncharges && $uid !== null) {
                $labStmt = $pdo->prepare(
                    'SELECT l.lab_id, l.lab_name, l.department
                     FROM lab_incharges li
                     JOIN labs l ON li.lab_id = l.lab_id
                     WHERE li.uid = :uid
                     ORDER BY l.lab_name ASC'
                );
                $labStmt->execute([':uid' => $uid]);
                $user['labs'] = $labStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Also check legacy or primary lab assignment in labs table
            if (empty($user['labs'])) {
                $labStmt = $pdo->prepare(
                    'SELECT lab_id, lab_name, department
                     FROM labs
                     WHERE incharge_uid = :uid OR LOWER(lab_incharge) = LOWER(:username)
                     ORDER BY lab_name ASC'
                );
                $labStmt->execute([':uid' => $uid, ':username' => $username]);
                $user['labs'] = $labStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        unset($user);

        return $users;
    }

    /**
     * Create a new Incharge user and optionally associate labs.
     * Returns the integer UID (or 0 if unavailable).
     */
    public function createIncharge(array $data, array $labIds = []): int
    {
        $pdo = $this->getPdo();

        $userId = $data['user_id'] ?? ('INC_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordHash = str_starts_with($password, '$2y$') || str_starts_with($password, '$argon2')
            ? $password
            : password_hash($password, PASSWORD_DEFAULT);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $contact = trim((string) ($data['contact_number'] ?? ''));
        $org = trim((string) ($data['org_name'] ?? ''));
        $mustChange = isset($data['must_change_password']) ? (int) $data['must_change_password'] : 1;

        $hasMustChange = $this->hasColumn('users', 'must_change_password');

        if ($hasMustChange) {
            $sql = 'INSERT INTO users (user_id, username, password, email, user_type, full_name, contact_number, org_name, must_change_password)
                    VALUES (:user_id, :username, :password, :email, :user_type, :full_name, :contact_number, :org_name, :must_change)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':password' => $passwordHash,
                ':email' => $email,
                ':user_type' => 'Incharge',
                ':full_name' => $fullName,
                ':contact_number' => $contact,
                ':org_name' => $org,
                ':must_change' => $mustChange,
            ]);
        } else {
            $sql = 'INSERT INTO users (user_id, username, password, email, user_type, full_name, contact_number, org_name)
                    VALUES (:user_id, :username, :password, :email, :user_type, :full_name, :contact_number, :org_name)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':password' => $passwordHash,
                ':email' => $email,
                ':user_type' => 'Incharge',
                ':full_name' => $fullName,
                ':contact_number' => $contact,
                ':org_name' => $org,
            ]);
        }

        $newUid = (int) $pdo->lastInsertId();

        // If UID is 0 (some MySQL setups when inserting explicit PKs), fetch by username
        if ($newUid === 0) {
            $userRow = $this->findByUsername($username);
            $newUid = (int) ($userRow['uid'] ?? 0);
        }

        if (!empty($labIds) && $newUid > 0) {
            $this->assignLabs($newUid, $labIds);
        }

        return $newUid;
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(int $uid, array $data): bool
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');

        $whereClause = $hasUidCol ? 'WHERE uid = :id' : 'WHERE user_id = :id';

        $sql = "UPDATE users
                SET full_name = :full_name,
                    email = :email,
                    contact_number = :contact_number,
                    org_name = :org_name
                {$whereClause}";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $uid,
            ':full_name' => trim((string) ($data['full_name'] ?? '')),
            ':email' => strtolower(trim((string) ($data['email'] ?? ''))),
            ':contact_number' => trim((string) ($data['contact_number'] ?? '')),
            ':org_name' => trim((string) ($data['org_name'] ?? '')),
        ]);
    }

    /**
     * Update password hash and optionally reset must_change_password.
     */
    public function updatePassword(int $uid, string $passwordHash, bool $clearMustChange = true): bool
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');
        $hasMustChange = $this->hasColumn('users', 'must_change_password');

        $whereClause = $hasUidCol ? 'WHERE uid = :id' : 'WHERE user_id = :id';

        if ($hasMustChange && $clearMustChange) {
            $sql = "UPDATE users SET password = :password, must_change_password = 0 {$whereClause}";
        } else {
            $sql = "UPDATE users SET password = :password {$whereClause}";
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $uid,
            ':password' => $passwordHash,
        ]);
    }

    /**
     * Assign labs to an incharge in lab_incharges and labs.incharge_uid.
     *
     * @param int $uid
     * @param array<string> $labIds
     */
    public function assignLabs(int $uid, array $labIds): void
    {
        $pdo = $this->getPdo();
        $hasLabIncharges = $this->hasTable('lab_incharges');

        if ($hasLabIncharges) {
            $delStmt = $pdo->prepare('DELETE FROM lab_incharges WHERE uid = :uid');
            $delStmt->execute([':uid' => $uid]);

            if (!empty($labIds)) {
                $insStmt = $pdo->prepare('INSERT IGNORE INTO lab_incharges (lab_id, uid) VALUES (:lab_id, :uid)');
                foreach ($labIds as $labId) {
                    $labId = trim((string) $labId);
                    if ($labId !== '') {
                        $insStmt->execute([':lab_id' => $labId, ':uid' => $uid]);
                    }
                }
            }
        }

        // Update primary incharge in labs table
        $user = $this->findByUid($uid);
        if ($user) {
            $hasInchargeUid = $this->hasColumn('labs', 'incharge_uid');
            if ($hasInchargeUid && !empty($labIds)) {
                $stmt = $pdo->prepare('UPDATE labs SET incharge_uid = :uid, lab_incharge = :username WHERE lab_id = :lab_id');
                foreach ($labIds as $labId) {
                    $stmt->execute([':uid' => $uid, ':username' => $user['username'], ':lab_id' => $labId]);
                }
            }
        }
    }

    /**
     * Check if username is unique.
     */
    public function isUsernameUnique(string $username, ?int $excludeUid = null): bool
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');

        if ($excludeUid !== null && $hasUidCol) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username AND uid != :exclude LIMIT 1');
            $stmt->execute([':username' => $username, ':exclude' => $excludeUid]);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
        }

        return !$stmt->fetchColumn();
    }

    /**
     * Check if email is unique.
     */
    public function isEmailUnique(string $email, ?int $excludeUid = null): bool
    {
        $pdo = $this->getPdo();
        $hasUidCol = $this->hasColumn('users', 'uid');

        if ($excludeUid !== null && $hasUidCol) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email AND uid != :exclude LIMIT 1');
            $stmt->execute([':email' => $email, ':exclude' => $excludeUid]);
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
        }

        return !$stmt->fetchColumn();
    }

    /**
     * Rate-limiting: checks whether IP / username is currently locked out.
     */
    public function isIpBlocked(string $username, string $ip = '', int &$remainingSeconds = 0): bool
    {
        if (function_exists('isLoginLocked')) {
            return isLoginLocked($username, $ip, $remainingSeconds);
        }
        return false;
    }

    /**
     * Rate-limiting: record failed login attempt.
     */
    public function recordFailedAttempt(string $username, string $ip = ''): void
    {
        if (function_exists('recordFailedLogin')) {
            recordFailedLogin($username, $ip);
        }
    }

    /**
     * Rate-limiting: clear failed login attempts on successful authentication.
     */
    public function clearFailedAttempts(string $username, string $ip = ''): void
    {
        if (function_exists('clearFailedLogin')) {
            clearFailedLogin($username, $ip);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE :col');
            $stmt->execute([':col' => $column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('SHOW TABLES LIKE :tbl');
            $stmt->execute([':tbl' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
