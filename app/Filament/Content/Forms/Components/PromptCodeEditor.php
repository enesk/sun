<?php

declare(strict_types=1);

namespace App\Filament\Content\Forms\Components;

use App\Guide\Llm\TemplateRenderer;
use Closure;
use Filament\Forms\Components\CodeEditor;

/**
 * Code-Editor mit Platzhalterhervorhebung (#54).
 *
 * Abgeleitet vom eingebauten Feld aus #43, nur mit eigener View und eigener
 * Alpine-Komponente: der Filament-Editor bietet keinen Haken fuer eigene
 * CodeMirror-Decorations, und zwei CodeMirror-Kopien in einem Editor gehen
 * nicht — Facets haengen an der Modulinstanz. Die PHP-Schnittstelle
 * (language(), wrap(), live()) bleibt deshalb unveraendert die von CodeEditor.
 *
 * Hervorgehoben werden {{var}}-Stellen in zwei Zustaenden: mit Beispielwert
 * in variables_json und ohne. Das Muster kommt aus TemplateRenderer, damit
 * Editor und Renderer nicht auseinanderlaufen koennen.
 */
class PromptCodeEditor extends CodeEditor
{
    /**
     * @var view-string
     */
    protected string $view = 'content.forms.prompt-code-editor';

    protected string|Closure|null $variablesStatePath = 'variables_json';

    /**
     * Feld mit den Beispielwerten — relativ zum selben Container oder mit
     * fuehrendem Schraegstrich absolut.
     */
    public function variablesStatePath(string|Closure|null $path): static
    {
        $this->variablesStatePath = $path;

        return $this;
    }

    public function getVariablesStatePath(): ?string
    {
        $path = $this->evaluate($this->variablesStatePath);

        if ($path === null || $path === '') {
            return null;
        }

        return $this->resolveRelativeStatePath($path);
    }

    /**
     * Dieselbe Platzhalter-Syntax wie der Renderer, ohne Delimiter.
     */
    public function getPlaceholderPattern(): string
    {
        return TemplateRenderer::placeholderPattern();
    }
}
