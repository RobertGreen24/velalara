<?php
namespace App\Http\Controllers;
use App\Models\HostingAccount;
use App\Services\SystemCommandService;
use Illuminate\Http\Request;
use Throwable;

class FileController extends Controller {
    public function __construct(private SystemCommandService $system) {}
    private function run(HostingAccount $account, string $action, array $data = []): array {
        abort_unless($account->status === 'active', 409);
        return $this->system->execute('file.'.$action, ['username' => $account->username] + $data);
    }
    public function index(Request $request, HostingAccount $account) {
        $path = $request->validate(['path' => ['nullable', 'string', 'max:1024']])['path'] ?? '';
        try { $entries = $this->run($account, 'list', ['path' => $path])['entries']; }
        catch (Throwable $e) { report($e); return redirect()->route('dashboard')->withErrors(['operation' => 'Unable to list directory.']); }
        return view('files', compact('account', 'path', 'entries'));
    }
    public function read(Request $request, HostingAccount $account) {
        $path = $request->validate(['path' => ['required', 'string', 'max:1024']])['path'];
        try { $content = base64_decode($this->run($account, 'read', ['path' => $path])['content'], true); }
        catch (Throwable $e) { report($e); return back()->withErrors(['operation' => 'Unable to read file.']); }
        return response($content, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="download"', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function write(Request $request, HostingAccount $account) {
        $data = $request->validate(['path' => ['required', 'string', 'max:1024'], 'content' => ['nullable', 'string', 'max:262144'], 'upload' => ['nullable', 'file', 'max:256']]);
        $content = isset($data['upload']) ? file_get_contents($data['upload']->getRealPath()) : ($data['content'] ?? '');
        if (strlen($content) > 262144) return back()->withErrors(['operation' => 'File exceeds 256 KiB.']);
        try { $this->run($account, 'write', ['path' => $data['path'], 'content' => base64_encode($content)]); }
        catch (Throwable $e) { report($e); return back()->withErrors(['operation' => 'Unable to write file.']); }
        return back()->with('message', 'File saved.');
    }
    public function delete(Request $request, HostingAccount $account) {
        $data = $request->validate(['path' => ['required', 'string', 'max:1024']]);
        try { $this->run($account, 'delete', $data); }
        catch (Throwable $e) { report($e); return back()->withErrors(['operation' => 'Unable to delete file.']); }
        return back()->with('message', 'File deleted.');
    }
}
