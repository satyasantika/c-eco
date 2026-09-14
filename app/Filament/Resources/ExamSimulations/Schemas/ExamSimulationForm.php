<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Schemas;

use App\Services\ExamSimulationBuilder;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ExamSimulationForm
{
    public static function configure(Schema $schema): Schema
    {
        $maxItems = (int) TestConfigProvisioner::adaptiveAttributes()['max_items'];

        return $schema->components([
            Section::make('Denah')
                ->description('Satu gelombang, satu jam. Siswa dibagi merata ke ruang. Hapus gelombang jika tes asli sudah tidak memakainya.')
                ->schema([
                    Select::make('preset')
                        ->label('Siap pakai')
                        ->options([
                            '20' => '20 siswa, 1 kelas',
                            '100' => '100 siswa, 4 kelas',
                            '300' => '300 siswa, 10 kelas',
                            'custom' => 'Atur sendiri',
                        ])
                        ->default('20')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $map = [
                                '20' => [20, 1, 1, 1],
                                '100' => [100, 4, 4, 2],
                                '300' => [300, 10, 10, 2],
                            ];
                            if (! isset($map[$state])) {
                                return;
                            }
                            [$students, $rooms, $pengawas, $operators] = $map[$state];
                            $set('students', $students);
                            $set('rooms', $rooms);
                            $set('pengawas_count', $pengawas);
                            $set('operators_count', $operators);
                        }),
                    TextInput::make('name')
                        ->label('Nama gelombang')
                        ->required()
                        ->maxLength(255)
                        ->default('Uji lapangan')
                        ->columnSpanFull()
                        ->helperText('Muncul di daftar simulasi dan nama kelas.'),
                    TextInput::make('students')
                        ->label('Jumlah siswa')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(ExamSimulationBuilder::MAX_STUDENTS)
                        ->default(20)
                        ->live(),
                    TextInput::make('rooms')
                        ->label('Jumlah kelas')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(ExamSimulationBuilder::MAX_ROOMS)
                        ->default(1)
                        ->live()
                        ->helperText(fn (Get $get): string => self::seatHint($get)),
                    DateTimePicker::make('starts_at')
                        ->label('Waktu mulai')
                        ->required()
                        ->seconds(false)
                        ->native(false)
                        ->timezone((string) config('app.timezone'))
                        ->default(now()->addHour()->startOfHour())
                        ->columnSpanFull()
                        ->helperText('Jam server. Kartu QR dan halaman siswa tertutup sebelum jam ini. Bisa diubah setelah gelombang dibuat.'),
                ])
                ->columns(2),

            Section::make('Daftar jaga')
                ->description('Akun baru, email sim{id}.op01@c-eco.test dan sim{id}.pw01@c-eco.test. Bukan akun tes asli.')
                ->schema([
                    TextInput::make('operators_count')
                        ->label('Jumlah operator')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(5)
                        ->default(1),
                    TextInput::make('pengawas_count')
                        ->label('Jumlah pengawas')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(ExamSimulationBuilder::MAX_ROOMS)
                        ->default(1)
                        ->helperText('Minimal sama dengan jumlah kelas. Satu pengawas memegang satu kartu QR.'),
                    TextInput::make('plain_password')
                        ->label('Kata sandi semua akun gelombang ini')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->default(ExamSimulationBuilder::DEFAULT_PASSWORD)
                        ->columnSpanFull()
                        ->helperText('Disimpan di catatan gelombang supaya bisa dibagikan ke pengawas uji.'),
                ])
                ->columns(2),

            Section::make('Isi paket')
                ->description('Persen X, XI, dan XII harus berjumlah 100. Butir diambil dari bank, tidak disalin.')
                ->schema([
                    TextInput::make('grade_share_x')
                        ->label('Persen jenjang X')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(34)
                        ->live()
                        ->suffix('%'),
                    TextInput::make('grade_share_xi')
                        ->label('Persen jenjang XI')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(33)
                        ->live()
                        ->suffix('%'),
                    TextInput::make('grade_share_xii')
                        ->label('Persen jenjang XII')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(33)
                        ->live()
                        ->suffix('%')
                        ->rules([self::shareSumRule()]),
                    TextInput::make('pool_size')
                        ->label('Jumlah butir dalam paket')
                        ->numeric()
                        ->required()
                        ->minValue($maxItems)
                        ->default(30)
                        ->live()
                        ->columnSpanFull()
                        ->helperText(fn (Get $get): string => MixedPackageComposer::preview(
                            (int) $get('grade_share_x'),
                            (int) $get('grade_share_xi'),
                            (int) $get('grade_share_xii'),
                            $get('pool_size') !== null && $get('pool_size') !== '' ? (int) $get('pool_size') : null,
                        )),
                ])
                ->columns(3),
        ]);
    }

    private static function seatHint(Get $get): string
    {
        $students = (int) $get('students');
        $rooms = (int) $get('rooms');

        if ($students < 1 || $rooms < 1) {
            return 'Siswa dibagi merata ke tiap kelas. Sisa kursi masuk kelas awal.';
        }

        try {
            $plan = ExamSimulationBuilder::seatPlan($students, $rooms);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return 'Pembagian kursi: '.implode(' + ', $plan).' = '.$students.'.';
    }

    private static function shareSumRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $sum = (int) $get('grade_share_x') + (int) $get('grade_share_xi') + (int) $get('grade_share_xii');
                if ($sum !== 100) {
                    $fail("Jumlah persen X + XI + XII harus 100 (sekarang {$sum}).");
                }
            };
        };
    }
}
