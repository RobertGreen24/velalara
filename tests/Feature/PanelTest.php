<?php
namespace Tests\Feature;
use App\Models\HostingAccount;
use App\Models\User;
use App\Services\SystemCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase {
    use RefreshDatabase;
    public function test_guests_cannot_access_management(): void {
        $this->get('/')->assertRedirect('/login');
        $this->post('/accounts', ['label' => 'test'])->assertRedirect('/login');
    }
    public function test_account_provisioning_uses_fixed_helper_action(): void {
        $this->mock(SystemCommandService::class)->shouldReceive('execute')->once()->withArgs(fn ($action, $data) => $action === 'account.create' && preg_match('/^vl_[a-z0-9]{12}$/', $data['username']))->andReturn([]);
        $this->actingAs(User::factory()->create())->post('/accounts', ['label' => 'Example'])->assertRedirect();
        $this->assertDatabaseHas('hosting_accounts', ['label' => 'Example', 'status' => 'active']);
    }
    public function test_invalid_domain_never_reaches_helper(): void {
        $this->mock(SystemCommandService::class)->shouldNotReceive('execute');
        $account = HostingAccount::create(['label' => 'Example', 'username' => 'vl_123456789abc', 'status' => 'active']);
        $this->actingAs(User::factory()->create())->post(route('domains.create', $account), ['domain' => "test.example; touch /tmp/pwn"])->assertSessionHasErrors('domain');
    }
    public function test_failed_provisioning_is_recorded(): void {
        $this->mock(SystemCommandService::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('failed'));
        $this->actingAs(User::factory()->create())->post('/accounts', ['label' => 'Example'])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('hosting_accounts', ['label' => 'Example', 'status' => 'failed']);
    }
}
