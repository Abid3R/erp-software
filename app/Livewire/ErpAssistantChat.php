<?php

namespace App\Livewire;

use App\Services\Gemini\ErpAssistant;
use App\Services\Gemini\Exceptions\GeminiException;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Floating, read-only AI assistant available on every admin page (injected via a
 * panel render hook). Holds the conversation in component state and delegates
 * every answer to {@see ErpAssistant}; it never writes to the ERP.
 *
 * #[Lazy] defers hydration until the browser reaches the component, so the
 * chat costs practically nothing on the initial page render.
 */
#[Lazy]
class ErpAssistantChat extends Component
{
    public bool $open = false;

    public string $draft = '';

    public bool $thinking = false;

    /** @var list<array{role: string, text: string}> */
    public array $messages = [];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function send(): void
    {
        $draft = trim($this->draft);
        if ($draft === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $draft];
        $this->draft = '';
        $this->thinking = true;

        try {
            $reply = app(ErpAssistant::class)->reply($this->historyForApi());
            $this->messages[] = ['role' => 'assistant', 'text' => $reply];
        } catch (GeminiException $e) {
            $this->messages[] = ['role' => 'assistant', 'text' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::warning('AI assistant error: '.$e->getMessage());
            $this->messages[] = ['role' => 'assistant', 'text' => 'Sorry — the assistant is temporarily unavailable. Please try again.'];
        } finally {
            $this->thinking = false;
        }
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    public function getAvailableProperty(): bool
    {
        return app(ErpAssistant::class)->isAvailable();
    }

    /**
     * Map display messages to the API shape (assistant → model).
     *
     * @return list<array{role: string, text: string}>
     */
    private function historyForApi(): array
    {
        return array_map(fn (array $m): array => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'text' => $m['text'],
        ], $this->messages);
    }

    public function render()
    {
        return view('livewire.erp-assistant-chat');
    }

    /** Cheap DOM stub while the full component defers/loads. */
    public function placeholder(): string
    {
        return '<div style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:50;'
            .'width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;'
            .'box-shadow:0 10px 15px -3px rgba(0,0,0,.15);"></div>';
    }
}
