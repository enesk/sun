import { Compartment, EditorState } from '@codemirror/state'
import { Decoration, EditorView, MatchDecorator, ViewPlugin, keymap } from '@codemirror/view'
import { basicSetup } from 'codemirror'
import { indentWithTab } from '@codemirror/commands'
import { json } from '@codemirror/lang-json'

/**
 * Prompt-Editor mit Platzhalterhervorhebung (#54).
 *
 * Eigener CodeMirror statt des eingebauten Feldes von Filament: dessen
 * Alpine-Komponente exportiert nur sich selbst, an die Extensions kommt man
 * nicht heran. Zwei CodeMirror-Kopien in einem Editor gehen ohnehin nicht —
 * Facets sind an die Modulinstanz gebunden.
 *
 * Kein Dunkelmodus-Thema: das Content-Panel steht auf darkMode(false)
 * (#30, die Token in theme.css sind auf hellen Grund gerechnet).
 *
 * Der Editor kennt zwei Zustaende: Platzhalter mit Beispielwert in
 * variables_json und Platzhalter ohne. Die Beispielwerte kommen live aus dem
 * benachbarten JSON-Feld, ohne Server-Umweg.
 */

/**
 * Schluessel eines Variablen-Objekts in Punkt-Notation.
 *
 * Deckungsgleich mit PromptRenderer::flatten(): verschachtelte Objekte
 * liefern zusaetzlich ihre Kinder, Listen bleiben ein Schluessel.
 */
function flattenKeys(value, prefix = '', keys = new Set()) {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return keys
    }

    for (const [key, nested] of Object.entries(value)) {
        const name = prefix === '' ? key : `${prefix}.${key}`
        keys.add(name)

        if (nested !== null && typeof nested === 'object' && !Array.isArray(nested)) {
            flattenKeys(nested, name, keys)
        }
    }

    return keys
}

function knownKeys(variables) {
    if (variables === null || variables === undefined || variables === '') {
        return new Set()
    }

    if (typeof variables === 'object') {
        return flattenKeys(variables)
    }

    try {
        return flattenKeys(JSON.parse(variables))
    } catch (error) {
        // Halb getipptes JSON ist der Normalfall waehrend des Schreibens:
        // dann gilt jeder Platzhalter als unbelegt, nichts bricht.
        return new Set()
    }
}

function placeholderExtension(pattern, keys) {
    const decorator = new MatchDecorator({
        regexp: new RegExp(pattern, 'g'),
        decoration: (match) =>
            Decoration.mark({
                class: keys.has(match[1])
                    ? 'cm-prompt-placeholder'
                    : 'cm-prompt-placeholder cm-prompt-placeholder-unknown',
            }),
    })

    return ViewPlugin.fromClass(
        class {
            constructor(view) {
                this.placeholders = decorator.createDeco(view)
            }

            update(update) {
                this.placeholders = decorator.updateDeco(update, this.placeholders)
            }
        },
        {
            decorations: (instance) => instance.placeholders,
        },
    )
}

export default function promptCodeEditorFormComponent({
    canWrap,
    isDisabled,
    isLive,
    isLiveDebounced,
    isLiveOnBlur,
    liveDebounce,
    language,
    placeholderPattern,
    state,
    variables,
}) {
    return {
        editor: null,
        placeholderCompartment: new Compartment(),
        isDocChanged: false,
        state,
        variables,

        init() {
            const debouncedCommit = Alpine.debounce(
                () => this.$wire.commit(),
                liveDebounce ?? 300,
            )

            this.editor = new EditorView({
                parent: this.$refs.editor,
                state: EditorState.create({
                    doc: this.state ?? '',
                    extensions: [
                        basicSetup,
                        keymap.of([indentWithTab]),
                        ...(canWrap ? [EditorView.lineWrapping] : []),
                        EditorState.readOnly.of(isDisabled),
                        EditorView.editable.of(!isDisabled),
                        EditorView.updateListener.of((viewUpdate) => {
                            if (!viewUpdate.docChanged) {
                                return
                            }

                            this.isDocChanged = true
                            this.state = viewUpdate.state.doc.toString()

                            if (!isLiveOnBlur && (isLive || isLiveDebounced)) {
                                debouncedCommit()
                            }
                        }),
                        EditorView.domEventHandlers({
                            blur: () => {
                                if (isLiveOnBlur && this.isDocChanged) {
                                    this.$wire.$commit()
                                }
                            },
                        }),
                        ...(language === 'json' ? [json()] : []),
                        this.placeholderCompartment.of(
                            placeholderExtension(placeholderPattern, knownKeys(this.variables)),
                        ),
                    ],
                }),
            })

            this.$watch('state', () => {
                if (this.state === undefined) {
                    return
                }

                if (this.editor.state.doc.toString() === this.state) {
                    return
                }

                this.editor.dispatch({
                    changes: {
                        from: 0,
                        to: this.editor.state.doc.length,
                        insert: this.state,
                    },
                })
            })

            // Ein neuer Beispielwert faerbt den Platzhalter sofort um.
            this.$watch('variables', () => {
                this.editor.dispatch({
                    effects: this.placeholderCompartment.reconfigure(
                        placeholderExtension(placeholderPattern, knownKeys(this.variables)),
                    ),
                })
            })
        },

        destroy() {
            if (this.editor) {
                this.editor.destroy()
                this.editor = null
            }
        },
    }
}
