<?php
namespace App\Http\Controllers;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Services\SystemCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class HostingController extends Controller {
    public function __construct(private SystemCommandService $system) {}
    public function index() { return view('dashboard', ['accounts' => HostingAccount::with(['domains', 'databases'])->latest()->get()]); }
    public function account(Request $request) {
        $data = $request->validate(['label' => ['required', 'string', 'max:100']]);
        $account = HostingAccount::create(['label' => $data['label'], 'username' => 'vl_'.Str::lower(Str::random(12)), 'status' => 'pending']);
        try {
            $this->system->execute('account.create', ['username' => $account->username]);
            $account->update(['status' => 'active']);
        } catch (Throwable $e) { $account->update(['status' => 'failed']); report($e); return back()->withErrors(['operation' => 'Account provisioning failed. See server logs and reconcile before retrying.']); }
        return back()->with('message', 'Account created.');
    }
    public function domain(Request $request, HostingAccount $account) {
        abort_unless($account->status === 'active', 409);
        $request->merge(['domain' => strtolower((string) $request->input('domain'))]);
        $data = $request->validate(['domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', Rule::unique('domains', 'name')]]);
        $domain = $account->domains()->create(['name' => $data['domain'], 'status' => 'pending']);
        try {
            $this->system->execute('domain.create', ['username' => $account->username, 'domain' => $domain->name]);
            $domain->update(['status' => 'active']);
        } catch (Throwable $e) { $domain->update(['status' => 'failed']); report($e); return back()->withErrors(['operation' => 'Domain provisioning failed. See server logs and reconcile before retrying.']); }
        return back()->with('message', 'Domain created. Point DNS at this server before issuing a certificate.');
    }
    public function certificate(Request $request, Domain $domain) {
        abort_unless($domain->status === 'active' && $domain->account->status === 'active', 409);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        try {
            $this->system->execute('certificate.issue', ['username' => $domain->account->username, 'domain' => $domain->name, 'email' => $data['email']]);
            $domain->update(['ssl' => true]);
        } catch (Throwable $e) { report($e); return back()->withErrors(['operation' => 'Certificate issuance failed. Check DNS, ports 80/443, and Certbot logs.']); }
        return back()->with('message', 'Certificate installed.');
    }
    public function database(HostingAccount $account) {
        abort_unless($account->status === 'active', 409);
        try {
            $result = $this->system->execute('database.create', ['username' => $account->username]);
            $account->databases()->create(['name' => $result['database'], 'db_user' => $result['db_user']]);
        } catch (Throwable $e) { report($e); return back()->withErrors(['operation' => 'Database provisioning failed. Check server logs and reconcile orphaned resources.']); }
        return back()->with('credentials', $result);
    }
}
