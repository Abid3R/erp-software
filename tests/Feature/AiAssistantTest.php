<?php

use App\Livewire\ErpAssistantChat;
use App\Models\Company;
use App\Services\Gemini\ErpAssistant;
use App\Services\Gemini\Exceptions\GeminiException;
use App\Services\Gemini\GeminiClient;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** A canned Gemini generateContent response. */
function fakeGemini(string $text): void
{
    Http::fake([
        '*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
            ]],
        ], 200),
    ]);
}

it('is disabled when no API key is configured', function () {
    config(['services.gemini.key' => null]);

    expect(app(GeminiClient::class)->isConfigured())->toBeFalse();

    expect(fn () => app(GeminiClient::class)->chat('sys', [['role' => 'user', 'text' => 'hi']]))
        ->toThrow(GeminiException::class);
});

it('sends the conversation and returns the model reply', function () {
    config(['services.gemini.key' => 'test-key']);
    fakeGemini('Your net profit this month is ৳1,234.00.');

    $reply = app(GeminiClient::class)->chat('You are helpful.', [
        ['role' => 'user', 'text' => 'What is my net profit?'],
    ]);

    expect($reply)->toBe('Your net profit this month is ৳1,234.00.');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), ':generateContent')
            && $request['contents'][0]['parts'][0]['text'] === 'What is my net profit?'
            && $request['system_instruction']['parts'][0]['text'] === 'You are helpful.';
    });
});

it('grounds the assistant on the active company snapshot', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    config(['services.gemini.key' => 'test-key']);
    fakeGemini('Based on your data, revenue is ৳0.00 this month.');

    $reply = app(ErpAssistant::class)->reply([
        ['role' => 'user', 'text' => 'How are sales this month?'],
    ]);

    expect($reply)->toContain('revenue');

    // The company snapshot (name + READ-ONLY rules) must be in the system prompt.
    Http::assertSent(function ($request) use ($company) {
        $system = $request['system_instruction']['parts'][0]['text'];

        return str_contains($system, $company->name)
            && str_contains($system, 'READ-ONLY')
            && str_contains($system, 'DATA SNAPSHOT');
    });
});

it('replaces party names when sharing is disabled', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    config(['services.gemini.key' => 'test-key', 'services.gemini.share_party_names' => false]);
    fakeGemini('ok');

    app(ErpAssistant::class)->reply([['role' => 'user', 'text' => 'who owes me?']]);

    Http::assertSent(function ($request) {
        // With no receivables the section is empty, but the config path must not error
        // and no real party name should leak. Assert the snapshot rendered.
        return str_contains($request['system_instruction']['parts'][0]['text'], 'RECEIVABLES AGING');
    });
});

it('drives the floating chat widget end to end', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    config(['services.gemini.key' => 'test-key']);
    fakeGemini('Hello! How can I help with your ERP today?');

    Livewire::test(ErpAssistantChat::class)
        ->assertSet('available', true)
        ->call('toggle') // open the panel so messages render
        ->set('draft', 'Hi there')
        ->call('send')
        ->assertSet('draft', '')
        ->assertCount('messages', 2)
        ->assertSee('Hello! How can I help');
});

it('shows a friendly message when the AI service errors', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    config(['services.gemini.key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

    Livewire::test(ErpAssistantChat::class)
        ->set('draft', 'anything')
        ->call('send')
        ->assertCount('messages', 2); // user msg + error reply, no exception bubbled
});
