import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { WebLinksAddon } from '@xterm/addon-web-links';
import { SearchAddon } from '@xterm/addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';

// one long-lived terminal+socket per server, parked in the @persist holder and
// borrowed into the overview slot while visible. the page never owns it, so
// power-state morphs and tab navigation can't tear it down.
const THEME = {
    background: 'rgba(19,26,32,0.7)', cursor: 'transparent', black: '#000000',
    red: '#E54B4B', green: '#9ECE58', yellow: '#FAED70', blue: '#396FE2',
    magenta: '#BB80B3', cyan: '#2DDAFD', white: '#d0d0d0',
    brightBlack: 'rgba(255,255,255,0.2)', brightRed: '#FF5370', brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B', brightBlue: '#82AAFF', brightMagenta: '#C792EA',
    brightCyan: '#89DDFF', brightWhite: '#ffffff', selection: '#FAF089',
};

class Console {
    constructor(uuid, opts) {
        this.uuid = uuid;
        // strip control bytes so a crafted server name can't smuggle ansi escapes
        // into the prelude and spoof the prompt line in the terminal.
        this.name = (opts.name || '').replace(/\p{Cc}/gu, '');
        this.fontSize = opts.fontSize || 14;
        this.fontFamily = opts.fontFamily || 'monospace';
        this.rows = opts.rows || 30;
        this.prelude = '\u001b[1m\u001b[33mpelican@' + this.name + ' ~ \u001b[0m';
        this.status = null;
        this.socket = null;
        this.token = null;
        this.opened = false;
        this.disposed = false;
        this.openRaf = null;

        const el = document.createElement('div');
        el.className = 'osconsole-terminal';
        this.element = el;

        this.terminal = new Terminal({
            fontSize: this.fontSize, fontFamily: this.fontFamily + ', monospace',
            lineHeight: 1.2, disableStdin: true, cursorStyle: 'underline',
            cursorInactiveStyle: 'underline', allowTransparency: true,
            rows: this.rows, theme: THEME,
        });
        this.fitAddon = new FitAddon();
        this.searchAddon = new SearchAddon();
        this.searchBar = new SearchBarAddon({ searchAddon: this.searchAddon });

        this.terminal.attachCustomKeyEventHandler((e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                navigator.clipboard.writeText(this.terminal.getSelection());
                return false;
            } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault(); this.searchBar.show(); return false;
            } else if (e.key === 'Escape') { this.searchBar.hidden(); }
            return true;
        });

        this.connect();
    }

    write(line, prelude = false) {
        this.terminal.writeln((prelude ? this.prelude : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');
    }

    async fetchToken() {
        const res = await fetch('/api/client/servers/' + this.uuid + '/websocket', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        });
        if (!res.ok) { throw new Error('ws token ' + res.status); }
        const body = await res.json();
        this.token = body.data.token;
        return body.data;
    }

    async connect() {
        let creds;
        try { creds = await this.fetchToken(); }
        catch (e) { window.Livewire?.dispatch('websocket-error'); return; }

        this.socket = new WebSocket(creds.socket);
        this.socket.onopen = () => this.auth();
        this.socket.onerror = () => window.Livewire?.dispatch('websocket-error');
        this.socket.onmessage = (ev) => this.onMessage(JSON.parse(ev.data));
    }

    auth() {
        this.socket?.send(JSON.stringify({ event: 'auth', args: [this.token] }));
    }

    onMessage({ event, args }) {
        switch (event) {
            case 'console output':
            case 'install output':
                this.write(args[0]); break;
            case 'daemon error':
                this.terminal.writeln(this.prelude + '\u001b[1m\u001b[41m' + args[0].replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m'); break;
            case 'transfer status':
                if (args[0] === 'failure') { this.write('Transfer has failed.', true); } break;
            case 'feature match':
                window.Livewire?.dispatch('mount-feature', { data: args[0] }); break;
            case 'status':
                this.status = args[0];
                this.write('Server marked as ' + args[0] + '...', true);
                window.Livewire?.dispatch('console-status', { state: args[0] });
                break;
            case 'stats':
                window.Livewire?.dispatch('store-stats', { data: args[0] }); break;
            case 'auth success':
                this.socket?.send(JSON.stringify({ event: 'send logs', args: [null] })); break;
            case 'token expiring':
            case 'token expired':
                this.fetchToken().then(() => this.auth()).catch(() => window.Livewire?.dispatch('websocket-error')); break;
        }
    }

    send(command) {
        this.socket?.send(JSON.stringify({ event: 'send command', args: [command] }));
    }

    setState(state) {
        this.socket?.send(JSON.stringify({ event: 'set state', args: [state] }));
    }

    attach(slot) {
        slot.appendChild(this.element);
        // open() reads the element's dimensions, so defer it until layout has
        // given the slot a height. on a fresh load attach can fire before the
        // first layout pass, so retry on the next frame until there is a box.
        const open = () => {
            if (this.disposed) { return; }
            // wait for layout to give the slot a width; xterm derives its own
            // height from the row count once opened. a detached element stays at
            // width 0 forever, so the disposed guard above stops the loop spinning.
            if (this.element.clientWidth === 0) { this.openRaf = requestAnimationFrame(open); return; }
            if (!this.opened) {
                this.terminal.loadAddon(this.fitAddon);
                this.terminal.loadAddon(new WebLinksAddon());
                this.terminal.loadAddon(this.searchAddon);
                this.terminal.loadAddon(this.searchBar);
                this.terminal.open(this.element);
                this.opened = true;
            }
            this.fitAddon.fit();
            if (this.status !== null) {
                window.Livewire?.dispatch('console-status', { state: this.status });
            }
        };
        this.openRaf = requestAnimationFrame(open);
    }

    dispose() {
        this.disposed = true;
        if (this.openRaf) { cancelAnimationFrame(this.openRaf); }
        try { this.socket?.close(); } catch (_) {}
        this.terminal.dispose();
        if (this.element.parentNode) { this.element.parentNode.removeChild(this.element); }
    }
}

const registry = {
    consoles: new Map(),
    current: null,
    holder: () => document.getElementById('osconsole-holder'),

    ensure(uuid, opts) {
        if (!this.consoles.has(uuid)) {
            const c = new Console(uuid, opts);
            this.holder()?.appendChild(c.element); // park until attached
            this.consoles.set(uuid, c);
        }
        this.current = uuid;
        return this.consoles.get(uuid);
    },
    attach(uuid, slot) { this.consoles.get(uuid)?.attach(slot); },
    detach(uuid) {
        const c = this.consoles.get(uuid);
        if (c) { this.holder()?.appendChild(c.element); }
    },
    send(uuid, cmd) { this.consoles.get(uuid)?.send(cmd); },
    setState(uuid, state) { this.consoles.get(uuid)?.setState(state); },
    dispose(uuid) {
        const c = this.consoles.get(uuid);
        if (c) { c.dispose(); this.consoles.delete(uuid); }
        if (this.current === uuid) { this.current = null; }
    },
    disposeAllExcept(uuidPrefix) {
        // the server url carries the short uuid while consoles are keyed by the
        // full uuid, so keep any console whose id starts with the url segment.
        for (const id of [...this.consoles.keys()]) {
            if (!uuidPrefix || !id.startsWith(uuidPrefix)) { this.dispose(id); }
        }
    },
    // attach to the console slot currently on the page, reading its config off
    // data attributes. the controller drives this rather than the blade so it
    // does not race alpine's x-init on a fresh load.
    mountCurrentSlot() {
        const slot = document.getElementById('osconsole-slot');
        if (!slot || !slot.dataset.uuid) { return; }
        this.ensure(slot.dataset.uuid, {
            name: slot.dataset.name || '',
            fontSize: parseInt(slot.dataset.fontSize || '14', 10),
            fontFamily: slot.dataset.fontFamily || 'monospace',
            rows: parseInt(slot.dataset.rows || '30', 10),
        });
        this.attach(slot.dataset.uuid, slot);
    },
};

window.OspiteConsole = registry;

// power buttons on the page head dispatch setServerState; the socket lives here now.
// register on livewire:init so Livewire is guaranteed present (the module may load first).
document.addEventListener('livewire:init', () => {
    window.Livewire.on('setServerState', ({ state, uuid }) => registry.setState(uuid, state));
});

// park the visible terminal back in the holder before a soft navigation swaps the page out.
document.addEventListener('livewire:navigating', () => {
    if (registry.current) { registry.detach(registry.current); }
});

document.addEventListener('livewire:navigated', () => {
    const m = window.location.pathname.match(/\/server\/([0-9a-f-]+)/i);
    const uuid = m ? m[1] : null;
    registry.disposeAllExcept(uuid);
    registry.mountCurrentSlot();
});

registry.mountCurrentSlot();
