<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Participant;
use App\Models\School;
use App\Models\User;
use App\Services\ParticipantImporter;
use App\Services\SchoolImporter;
use App\Services\UserImporter;
use App\Support\DelimitedTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_csv_tsv_and_semicolon_paste(): void
    {
        $csv = DelimitedTable::parse("name,city\nSMA A,Tasik\n", ['name'], [], ['city']);
        $this->assertSame('SMA A', $csv[0]['name']);

        $tsv = DelimitedTable::parse("name\tcity\nSMA B\tGarut\n", ['name'], [], ['city']);
        $this->assertSame('Garut', $tsv[0]['city']);

        $id = DelimitedTable::parse("nama;kota\nSMA C;Ciamis\n", ['name'], [
            'name' => ['nama'],
            'city' => ['kota'],
        ], ['city']);
        $this->assertSame('SMA C', $id[0]['name']);
        $this->assertSame('Ciamis', $id[0]['city']);
    }

    public function test_it_strips_a_bom_and_accepts_indonesian_headers(): void
    {
        $text = "\xEF\xBB\xBFnama,kota\nSMAN 1,Tasikmalaya\n";
        $rows = DelimitedTable::parse($text, ['name'], [
            'name' => ['nama'],
            'city' => ['kota'],
        ], ['city']);

        $this->assertSame('SMAN 1', $rows[0]['name']);
    }

    public function test_a_missing_header_rejects_the_paste(): void
    {
        $this->expectException(RuntimeException::class);
        DelimitedTable::parse("sekolah,kelas\nSMA,XI\n", ['name', 'city']);
    }

    public function test_schools_are_created_and_existing_names_are_skipped(): void
    {
        School::query()->create(['name' => 'SMA Negeri 1 Tasikmalaya', 'city' => 'Tasikmalaya']);

        $result = app(SchoolImporter::class)->importText(
            "name,city\nSMA Negeri 1 Tasikmalaya,Bandung\nSMA Negeri 2 Tasikmalaya,Tasikmalaya\n"
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Tasikmalaya', School::query()->where('name', 'SMA Negeri 1 Tasikmalaya')->value('city'));
        $this->assertTrue(School::query()->where('name', 'SMA Negeri 2 Tasikmalaya')->exists());
    }

    public function test_participants_paste_creates_schools_and_skips_duplicate_codes(): void
    {
        $first = app(ParticipantImporter::class)->importText(
            "school,class_name,student_code,display_name\nSMA Uji,XI IPS 1,0001,Sinta Lestari\n"
        );
        $again = app(ParticipantImporter::class)->importText(
            "school,class_name,student_code,display_name\nSMA Uji,XI IPS 1,0001,Nama Lain\nSMA Uji,XI IPS 1,0002,Raka Putra\n"
        );

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $again['created']);
        $this->assertSame(1, $again['skipped']);
        $this->assertSame(2, Participant::query()->count());
        $this->assertSame('Sinta Lestari', Participant::query()->where('student_code', '0001')->value('display_name'));
    }

    public function test_a_blank_participant_field_rejects_the_whole_batch(): void
    {
        try {
            app(ParticipantImporter::class)->importText(
                "school,class_name,student_code,display_name\nSMA Uji,XI IPS 1,0001,Ada\nSMA Uji,XI IPS 1,,Kosong\n"
            );
            $this->fail('Berkas dengan kolom kosong harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('student_code', $e->getMessage());
        }

        $this->assertSame(0, Participant::query()->count());
    }

    public function test_users_import_creates_staff_and_rejects_admin_role(): void
    {
        $ok = app(UserImporter::class)->importText(
            "name,email,role,password\nSiti,siti@sekolah.sch.id,pengawas,rahasia123\nBudi,budi@sekolah.sch.id,operator,rahasia123\n",
        );

        $this->assertSame(2, $ok['created']);
        $this->assertTrue(User::query()->where('email', 'siti@sekolah.sch.id')->where('role', UserRole::Pengawas)->exists());

        try {
            app(UserImporter::class)->importText(
                "name,email,role,password\nRoot,root@c-eco.test,admin,rahasia123\n"
            );
            $this->fail('Admin mass import should fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('admin', $e->getMessage());
        }

        $this->assertFalse(User::query()->where('email', 'root@c-eco.test')->exists());
    }

    public function test_existing_staff_email_is_skipped_and_password_is_unchanged(): void
    {
        $user = User::factory()->pengawas()->create([
            'email' => 'siti@sekolah.sch.id',
            'password' => 'lama-lama-1',
        ]);
        $hash = $user->password;

        $result = app(UserImporter::class)->importText(
            "name,email,role,password\nSiti Baru,siti@sekolah.sch.id,pengawas,baru-baru-9\n"
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_admin_can_import_schools_from_paste_and_csv_in_the_panel(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListSchools::class)
            ->assertActionVisible('importSchools')
            ->callAction('importSchools', [
                'source' => 'paste',
                'paste' => "name,city\nSMA Copas,Tasikmalaya\n",
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(School::query()->where('name', 'SMA Copas')->exists());

        $file = UploadedFile::fake()->createWithContent(
            'sekolah.csv',
            "name,city\nSMA Unggah,Garut\n",
        );

        Livewire::actingAs($admin)
            ->test(ListSchools::class)
            ->callAction('importSchools', [
                'source' => 'file',
                'file' => $file,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(School::query()->where('name', 'SMA Unggah')->exists());
    }

    public function test_admin_can_import_participants_and_users_from_the_panel(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListParticipants::class)
            ->assertActionVisible('importParticipants')
            ->callAction('importParticipants', [
                'source' => 'paste',
                'city' => 'Tasikmalaya',
                'paste' => "school,class_name,student_code,display_name\nSMA Panel,XI IPS 1,2026001,Sinta Lestari\n",
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('Tasikmalaya', School::query()->where('name', 'SMA Panel')->value('city'));
        $this->assertTrue(Participant::query()->where('student_code', '2026001')->exists());

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertActionVisible('importUsers')
            ->callAction('importUsers', [
                'source' => 'paste',
                'default_password' => 'ekonomi1234',
                'paste' => "name,email,role\nDewi,dewi@sekolah.sch.id,peneliti\n",
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(User::query()->where('email', 'dewi@sekolah.sch.id')->where('role', UserRole::Peneliti)->exists());
    }

    public function test_operator_can_import_roster_but_not_users(): void
    {
        $operator = User::factory()->operator()->create();

        Livewire::actingAs($operator)
            ->test(ListSchools::class)
            ->assertActionVisible('importSchools');

        Livewire::actingAs($operator)
            ->test(ListParticipants::class)
            ->assertActionVisible('importParticipants');

        $this->actingAs($operator)->get('/admin/users')->assertForbidden();
    }

    public function test_pengawas_cannot_import_roster(): void
    {
        $pengawas = User::factory()->pengawas()->create();

        $this->actingAs($pengawas)->get('/admin/schools')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/participants')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/users')->assertForbidden();
    }

    public function test_artisan_participant_import_still_uses_the_importer(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ceco-roster').'.csv';
        file_put_contents($path, "school,class_name,student_code,display_name\nSMA Artisan,XI,0001,Ada\n");

        $this->artisan('cat:import-participants', ['file' => $path, '--city' => 'Tasikmalaya'])
            ->assertSuccessful();

        $this->assertSame(1, Participant::query()->count());
        $this->assertSame('Tasikmalaya', School::query()->value('city'));
    }
}
