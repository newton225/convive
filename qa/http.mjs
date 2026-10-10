import http from 'node:http';

/**
 * Un client HTTP minimal pour les campagnes de securite, de performance et de charge : un pot de
 * cookies par session, aucune redirection suivie (on veut voir la reponse brute), le Host et les
 * en-tetes librement choisis (le serveur de test repond a `*.localhost` sur 127.0.0.1).
 */
export class Session {
    constructor(baseUrl, { host = null } = {}) {
        this.base = new URL(baseUrl);
        this.host = host;
        this.cookies = new Map();
    }

    cookieHeader() {
        return [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; ');
    }

    request(method, path, { headers = {}, body = null, host = this.host, timeout = 60000 } = {}) {
        return new Promise((resolve, reject) => {
            const started = performance.now();
            const isForm = body !== null && typeof body !== 'string' && !Buffer.isBuffer(body);
            const payload = body === null ? null : isForm ? new URLSearchParams(body).toString() : body;

            const request = http.request(
                {
                    host: this.base.hostname,
                    port: this.base.port,
                    method,
                    path,
                    timeout,
                    headers: {
                        Host: host ?? this.base.host,
                        Accept: 'text/html,application/xhtml+xml',
                        ...(this.cookies.size ? { Cookie: this.cookieHeader() } : {}),
                        ...(isForm ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}),
                        ...(payload !== null ? { 'Content-Length': Buffer.byteLength(payload) } : {}),
                        ...headers,
                    },
                },
                (response) => {
                    const chunks = [];

                    response.on('data', (chunk) => chunks.push(chunk));
                    response.on('end', () => {
                        for (const line of response.headers['set-cookie'] ?? []) {
                            const [pair] = line.split(';');
                            const index = pair.indexOf('=');
                            const name = pair.slice(0, index);
                            const value = pair.slice(index + 1);

                            if (value === '' || /expires=Thu, 01 Jan 1970/i.test(line)) {
                                this.cookies.delete(name);
                            } else {
                                this.cookies.set(name, value);
                            }
                        }

                        const buffer = Buffer.concat(chunks);

                        resolve({
                            status: response.statusCode,
                            headers: response.headers,
                            setCookie: response.headers['set-cookie'] ?? [],
                            body: buffer.toString('utf8'),
                            ms: performance.now() - started,
                            bytes: buffer.length,
                        });
                    });
                },
            );

            request.on('timeout', () => request.destroy(new Error('timeout')));
            request.on('error', reject);

            if (payload !== null) {
                request.write(payload);
            }

            request.end();
        });
    }

    get(path, options) {
        return this.request('GET', path, options);
    }

    post(path, body = {}, options = {}) {
        return this.request('POST', path, { ...options, body });
    }

    /** Le jeton CSRF de la session : le cookie XSRF-TOKEN, decode. */
    xsrf() {
        const raw = this.cookies.get('XSRF-TOKEN');

        return raw ? decodeURIComponent(raw) : null;
    }

    /** Ouvre une page pour obtenir cookie de session et jeton CSRF. */
    async warm(path = '/login') {
        return this.get(path);
    }

    /** POST avec le jeton CSRF de la session (en-tete X-XSRF-TOKEN). */
    async postWithCsrf(path, body = {}, options = {}) {
        const token = this.xsrf();

        return this.post(path, body, {
            ...options,
            headers: { ...(token ? { 'X-XSRF-TOKEN': token } : {}), ...(options.headers ?? {}) },
        });
    }

    /** Connexion par le formulaire, comme le navigateur. */
    async login(email, password = 'password') {
        await this.warm('/login');

        return this.postWithCsrf('/login', { email, password });
    }
}

export function percentile(sorted, p) {
    if (sorted.length === 0) {
        return 0;
    }

    const index = Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1);

    return sorted[Math.max(0, index)];
}
