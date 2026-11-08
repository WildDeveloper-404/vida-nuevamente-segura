(() => {
    const panel = document.getElementById('chat-panel'), toggle = document.getElementById('chat-toggle');
    if (!panel) return;
    const input = document.getElementById('chat-input'), log = document.getElementById('chat-messages'), form = document.getElementById('chat-form');
    const show = open => { panel.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); if (open) input.focus(); };
    toggle.addEventListener('click', () => show(panel.hidden));
    document.getElementById('chat-close').addEventListener('click', () => show(false));
    const bubble = (text, user = false) => { const p = document.createElement('p'); p.className = 'chat-bubble' + (user ? ' user' : ''); p.textContent = text; log.append(p); log.scrollTop = log.scrollHeight; };
    let busy = false;
    const send = async message => {
        if (!message.trim() || busy) return;
        busy = true; bubble(message, true); input.value = ''; form.querySelector('button').disabled = true;
        try {
            const response = await fetch(form.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ mensaje: message }),
            });
            const data = await response.json();
            bubble(response.ok ? data.respuesta : (data.message || 'No se pudo procesar el mensaje.'));
            if (response.ok) renderActions(data);
        }
        catch { bubble('No hay conexión con el asistente. Inténtelo nuevamente.'); }
        finally { busy = false; form.querySelector('button').disabled = false; }
    };
    const renderActions = ({ opciones = [], enlace }) => {
        const actions = document.createElement('div');
        actions.className = 'chat-quick';
        for (const option of opciones) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-primary';
            button.textContent = option;
            button.addEventListener('click', () => send(option));
            actions.append(button);
        }
        if (enlace) {
            const url = new URL(enlace.url, window.location.origin);
            if (url.origin === window.location.origin) {
                const link = document.createElement('a');
                link.className = 'btn btn-sm btn-outline-primary';
                link.href = url.href;
                link.textContent = enlace.texto;
                actions.append(link);
            }
        }
        if (actions.childElementCount) {
            log.append(actions);
            log.scrollTop = log.scrollHeight;
        }
    };
    form.addEventListener('submit', e => { e.preventDefault(); send(input.value); });
    document.querySelectorAll('[data-chat]').forEach(button => button.addEventListener('click', () => send(button.dataset.chat)));
})();
