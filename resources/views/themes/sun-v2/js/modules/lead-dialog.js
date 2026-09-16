/*
 * Anfrage-Dialog auf dem Firmenprofil (Vorlage sun-v2--profil.html).
 *
 * Headless an die oeffentliche Funnel-Runtime-API des Leadsystems angebunden:
 * Dialog oeffnet -> POST /sessions, "Weiter" -> PATCH /answers, Ende der Strecke
 * -> POST /submit. Welcher Schritt folgt, entscheidet allein der Server
 * (current_step bzw. phase 'done' der Antwort); Bedingungen werden hier nie
 * ausgewertet. 422-Fehler landen direkt unter den Feldern.
 *
 * Markup (partials/sun/lead-dialog): je Schritt ein [data-step="<position>"],
 * darin je Frage ein Element mit data-question-key und data-question-type,
 * Fehlerausgabe in [data-error-for="<key>"]. Titel des Schritts optional in
 * data-step-title, der Kontaktschritt am Dialog in data-contact-step.
 */

// Texte kommen fertig uebersetzt aus data-texts (partials/sun/lead-dialog, portal.request.*)
const SESSION_KEY = 'wl_session';

class ApiError extends Error {
    constructor(status, message, validation = null) {
        super(message);
        this.status = status;
        this.validation = validation;
    }
}

function fieldsOf(question) {
    return question.matches('input, textarea, select')
        ? [question]
        : [...question.querySelectorAll('input, textarea, select')];
}

// Antworten eines Schritts je Frage-Schluessel, Wertform nach Fragetyp
function answersFromStep(stepElement) {
    const answers = {};

    stepElement.querySelectorAll('[data-question-key]').forEach((question) => {
        const fields = fieldsOf(question);
        const key = question.dataset.questionKey;

        switch (question.dataset.questionType) {
            case 'info':
                return;
            case 'single_choice':
            case 'image_choice':
                answers[key] = fields.find((field) => field.checked)?.value ?? null;

                return;
            case 'multi_choice':
                answers[key] = fields.filter((field) => field.checked).map((field) => field.value);

                return;
            case 'consent':
                answers[key] = fields.some((field) => field.checked);

                return;
            case 'number':
            case 'slider': {
                const value = fields[0]?.value.trim() ?? '';
                answers[key] = value === '' ? null : Number(value);

                return;
            }
            default:
                answers[key] = fields[0]?.value.trim() || null;
        }
    });

    return answers;
}

export function initLeadDialog() {
    const dialog = document.getElementById('leadDialog');
    const form = document.getElementById('leadForm');

    if (!dialog || !form) {
        return;
    }

    const apiBase = (dialog.dataset.leadApi || '').replace(/\/$/, '');
    const token = dialog.dataset.leadToken || '';
    // Funnel-Feld firmenprofil ist Text: Profil-Link des Betriebs, nie vom Nutzer
    let companyKey = null;
    try {
        const profile = JSON.parse(dialog.dataset.companyKey || 'null');
        companyKey = profile ? String(profile.url || profile.slug || '') : null;
    } catch {
        companyKey = null;
    }
    const steps = [...form.querySelectorAll('[data-step]')];
    const questionSteps = steps.filter((el) => el.dataset.step !== 'done');
    const firstStep = questionSteps[0]?.dataset.step;
    const submitStep = dialog.dataset.contactStep || questionSteps[questionSteps.length - 1]?.dataset.step;
    const body = form.querySelector('[data-lead-body]');
    const nextButton = form.querySelector('[data-next]');
    const prevButton = form.querySelector('[data-prev]');
    const errorBox = form.querySelector('[data-lead-error]');
    const texts = JSON.parse(dialog.dataset.texts);

    // Besuchte Schritte im Browser; letzter Eintrag ist der sichtbare Schritt
    let history = [firstStep];
    // Schritt, auf dem die Sitzung beim Server steht
    let serverStep = null;
    let sessionToken = sessionStorage.getItem(SESSION_KEY);
    let busy = false;

    const current = () => history[history.length - 1];
    const stepElement = (step) => steps.find((el) => el.dataset.step === String(step));

    async function api(method, path, payload) {
        let response;

        try {
            response = await fetch(`${apiBase}${path}`, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: payload ? JSON.stringify(payload) : undefined,
            });
        } catch {
            throw new ApiError(0, texts.errors.offline);
        }

        const json = await response.json().catch(() => ({}));

        if (response.status === 422) {
            throw new ApiError(422, json.message || '', json.errors || {});
        }

        if (!response.ok) {
            throw new ApiError(response.status, json.message || '');
        }

        return json.data;
    }

    // Kampagnenparameter der Seitenadresse gehen beim Sitzungsstart mit
    function utmParams() {
        const params = new URLSearchParams(window.location.search);
        const utm = {};
        params.forEach((value, key) => {
            if (key.startsWith('utm_')) {
                utm[key] = value;
            }
        });

        return Object.keys(utm).length ? utm : null;
    }

    async function startSession() {
        const query = new URLSearchParams(utmParams() || {}).toString();
        const state = await api('POST', `/funnels/${token}/sessions${query ? `?${query}` : ''}`,
            sessionToken ? { session_token: sessionToken } : {});
        sessionToken = state.session_token;
        serverStep = String(state.current_step);
        sessionStorage.setItem(SESSION_KEY, sessionToken);

        return state;
    }

    function forgetSession() {
        sessionToken = null;
        serverStep = null;
        sessionStorage.removeItem(SESSION_KEY);
    }

    function patchAnswers(step) {
        return api('PATCH', `/funnels/${token}/sessions/${sessionToken}/answers`, {
            answers: { ...answersFromStep(stepElement(step)), firmenprofil: companyKey },
        });
    }

    /*
     * Der Server fuehrt current_step nur vorwaerts. Steht der Browser nach
     * "Zurueck" (oder einer fortgesetzten Sitzung) woanders, startet eine frische
     * Sitzung und die Antworten der besuchten Schritte davor gehen erneut hin.
     * Die Sitzung traegt danach nur Antworten des tatsaechlichen Wegs.
     */
    async function syncServer() {
        forgetSession();
        await startSession();

        for (const [index, step] of history.slice(0, -1).entries()) {
            try {
                const state = await patchAnswers(step);
                serverStep = String(state.current_step);
            } catch (error) {
                if (error.status === 422) {
                    history = history.slice(0, index + 1);
                    show(step);
                }
                throw error;
            }
        }

        if (serverStep !== current()) {
            throw new ApiError(0, texts.errors.generic);
        }
    }

    function setBusy(value) {
        busy = value;
        nextButton.disabled = value;
        nextButton.setAttribute('aria-busy', String(value));
    }

    function clearErrors() {
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('.border-red-500').forEach((el) => el.classList.remove('border-red-500'));
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    }

    function showGeneralError(error) {
        const messages = {
            429: texts.errors.rateLimited,
            403: texts.errors.forbidden,
        };
        errorBox.textContent = messages[error.status] || error.message || texts.errors.generic;
        errorBox.classList.remove('hidden');
    }

    function showValidation(errors) {
        let first = null;

        Object.entries(errors).forEach(([key, list]) => {
            // Server meldet answers.<key> bzw. answers.<key>.<index>
            const field = key.replace(/^answers\./, '').split('.')[0];
            const message = Array.isArray(list) ? list[0] : String(list);
            const question = form.querySelector(`[data-question-key="${CSS.escape(field)}"]`);
            const target = form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
            const inputs = question ? fieldsOf(question) : [];

            if (target) {
                target.textContent = message;
                target.classList.remove('hidden');
            } else {
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
            }

            inputs.forEach((input) => {
                input.setAttribute('aria-invalid', 'true');
                if (input.classList.contains('input')) {
                    input.classList.add('border-red-500');
                }
            });

            first = first || inputs[0];
        });

        first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function show(step) {
        const done = step === 'done';
        const element = stepElement(step);
        steps.forEach((el) => el.classList.toggle('hidden', el !== element));
        body.scrollTop = 0;
        document.getElementById('leadTitle').textContent = done
            ? texts.titles.done
            : (element?.dataset.stepTitle || texts.titles?.[step] || '');
        document.getElementById('leadStep').textContent = done
            ? texts.steps.done
            : String(texts.step || '').replace(':schritt', history.length);
        prevButton.classList.toggle('hidden', done || history.length < 2);
        nextButton.textContent = done ? texts.close : (step === submitStep ? texts.submit : texts.next);
        form.querySelector('[data-privacy]').classList.toggle('hidden', done);
    }

    async function open() {
        dialog.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        clearErrors();
        history = [firstStep];
        show(firstStep);

        if (!token || !apiBase) {
            showGeneralError({ status: 0, message: texts.errors.unavailable });

            return;
        }

        try {
            setBusy(true);
            await startSession();
        } catch (error) {
            // Abgelaufene Sitzung aus dem Speicher: einmal frisch starten
            if (sessionToken && error.status !== 429) {
                forgetSession();
                try {
                    await startSession();
                } catch (retry) {
                    showGeneralError(retry);
                }
            } else {
                showGeneralError(error);
            }
        } finally {
            setBusy(false);
        }
    }

    function close() {
        dialog.classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function submit() {
        const step = current();
        await api('POST', `/funnels/${token}/sessions/${sessionToken}/submit`, {
            answers: { ...answersFromStep(stepElement(step)), firmenprofil: companyKey },
            website: form.querySelector('[name="website"]')?.value ?? '',
        });
        forgetSession();
        history.push('done');
        show('done');
    }

    async function advance() {
        if (serverStep !== current()) {
            await syncServer();
        }

        const state = await patchAnswers(current());
        serverStep = String(state.current_step);

        // Kein weiterer Schritt: Strecke absenden
        if (state.phase === 'done') {
            await submit();

            return;
        }

        history.push(serverStep);
        show(serverStep);
    }

    async function next() {
        if (busy) {
            return;
        }

        if (current() === 'done') {
            close();

            return;
        }

        clearErrors();

        if (!sessionToken) {
            showGeneralError({ status: 0, message: texts.errors.unavailable });

            return;
        }

        setBusy(true);

        try {
            try {
                await advance();
            } catch (error) {
                // Sitzung unterwegs abgelaufen: einmal mit frischer Sitzung wiederholen
                if (error.status !== 404) {
                    throw error;
                }
                serverStep = null;
                await advance();
            }
        } catch (error) {
            if (error.status === 422) {
                showValidation(error.validation || {});
            } else {
                showGeneralError(error);
            }
        } finally {
            setBusy(false);
        }
    }

    document.querySelectorAll('[data-open-lead]').forEach((button) => button.addEventListener('click', open));
    dialog.querySelectorAll('[data-close-lead]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.classList.contains('hidden')) {
            close();
        }
    });
    prevButton.addEventListener('click', () => {
        if (history.length < 2) {
            return;
        }
        clearErrors();
        history.pop();
        show(current());
    });
    nextButton.addEventListener('click', next);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        next();
    });
}
