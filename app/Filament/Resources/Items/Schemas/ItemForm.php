<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Butir')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Kode adalah identitas butir yang dipakai seeder; tidak bisa diubah.'),
                        Select::make('dimension_id')
                            ->label('Dimensi')
                            ->relationship('dimension', 'name')
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(['active' => 'Aktif', 'retired' => 'Dipensiunkan'])
                            ->required()
                            ->helperText('Butir tidak pernah dihapus. Pensiunkan bila tidak layak pakai — data sesi lama tetap utuh.'),
                        TextInput::make('topic')->label('Topik'),
                        TextInput::make('semester')->label('Semester'),
                        TextInput::make('bloom_level')->label('Level Bloom'),
                    ])
                    ->columns(2),

                Section::make('Isi')
                    ->schema([
                        Textarea::make('stem_html')
                            ->label('Stem (HTML)')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull(),
                        Textarea::make('learning_objective')->label('Tujuan pembelajaran')->rows(2)->columnSpanFull(),
                        Textarea::make('indicator')->label('Indikator')->rows(2)->columnSpanFull(),
                        TextInput::make('media_path')->label('Berkas media')->columnSpanFull(),
                    ]),

                Section::make('Opsi jawaban')
                    ->description('Tepat satu opsi harus ditandai sebagai kunci. Urutan di sini adalah urutan asli; siswa melihatnya teracak.')
                    ->schema([
                        Repeater::make('options')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('label')
                                    ->label('Label')
                                    ->required()
                                    ->maxLength(1)
                                    ->disabled()
                                    ->dehydrated(),
                                Textarea::make('body_html')
                                    ->label('Isi opsi')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpan(2),
                                Toggle::make('is_key')->label('Kunci'),
                                TextInput::make('display_order')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(5)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->rules([
                                // Butir tanpa kunci tunggal tidak bisa dinilai;
                                // sesi yang memuatnya akan rusak diam-diam.
                                fn (): callable => function (string $attribute, mixed $value, callable $fail): void {
                                    $keys = Collection::make($value)->where('is_key', true)->count();

                                    if ($keys !== 1) {
                                        $fail("Butir harus punya tepat satu kunci, sekarang ada {$keys}.");
                                    }
                                },
                            ]),
                    ]),

                Section::make('Catatan sumber')
                    ->collapsed()
                    ->schema([
                        Textarea::make('source_note')
                            ->label('Sumber dan peringatan ekstraksi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
