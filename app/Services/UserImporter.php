<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DelimitedTable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserImporter
{
    /** @var list<string> */
    public const REQUIRED = ['name', 'email', 'role'];

    /** @var list<string> */
    public const OPTIONAL = ['password'];

    /** @var array<string, list<string>> */
    public const ALIASES = [
        'name' => ['nama'],
        'email' => ['surel'],
        'role' => ['peran'],
        'password' => ['sandi', 'kata_sandi'],
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function import(array $rows, ?string $defaultPassword = null): array
    {
        $defaultPassword = ($defaultPassword !== null && $defaultPassword !== '') ? $defaultPassword : null;
        $problems = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $row['_line'] ?? (string) ($index + 2);
            $name = trim($row['name'] ?? '');
            $email = mb_strtolower(trim($row['email'] ?? ''));
            $role = $this->parseRole(trim($row['role'] ?? ''));
            $password = (string) ($row['password'] ?? '');

            if ($name === '') {
                $problems[] = "baris {$line}: nama kosong";
            }

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $problems[] = "baris {$line}: email tidak sah";
            } elseif (isset($seen[$email])) {
                $problems[] = "baris {$line}: email berulang dalam berkas ini ({$email})";
            }

            if ($role === 'admin') {
                $problems[] = "baris {$line}: peran admin tidak boleh diimpor massal";
                $role = null;
            } elseif (! $role instanceof UserRole) {
                $problems[] = "baris {$line}: peran harus operator, pengawas, atau peneliti";
            }

            if ($password === '') {
                $password = $defaultPassword ?? '';
            }

            if (strlen($password) < 8) {
                $problems[] = "baris {$line}: kata sandi minimal 8 karakter (isi kolom password atau sandi default)";
            }

            $seen[$email] = true;
            $rows[$index]['name'] = $name;
            $rows[$index]['email'] = $email;
            $rows[$index]['role'] = $role;
            $rows[$index]['password'] = $password;
        }

        if ($problems !== []) {
            throw new RuntimeException("Impor dibatalkan:\n  - ".implode("\n  - ", $problems));
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$created, &$skipped): void {
            foreach ($rows as $row) {
                $existing = User::query()->where('email', $row['email'])->first();

                if ($existing instanceof User) {
                    if ($existing->exam_simulation_id !== null) {
                        throw new RuntimeException("Email {$row['email']} sudah dipakai akun simulasi.");
                    }

                    $skipped++;
                    continue;
                }

                User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'password' => $row['password'],
                    'is_active' => true,
                    'exam_simulation_id' => null,
                ]);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array{created: int, skipped: int} */
    public function importText(string $text, ?string $defaultPassword = null): array
    {
        return $this->import(
            DelimitedTable::parse($text, self::REQUIRED, self::ALIASES, self::OPTIONAL),
            $defaultPassword,
        );
    }

    /** @return array{created: int, skipped: int} */
    public function importFile(string $path, ?string $defaultPassword = null): array
    {
        return $this->import(
            DelimitedTable::fromFile($path, self::REQUIRED, self::ALIASES, self::OPTIONAL),
            $defaultPassword,
        );
    }

    private function parseRole(string $raw): UserRole|string|null
    {
        $key = mb_strtolower(trim($raw));

        return match ($key) {
            'operator' => UserRole::Operator,
            'pengawas' => UserRole::Pengawas,
            'peneliti', 'researcher' => UserRole::Peneliti,
            'admin' => 'admin',
            default => null,
        };
    }
}
