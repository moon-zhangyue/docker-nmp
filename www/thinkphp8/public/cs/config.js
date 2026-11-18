// 智能客服前端配置
window.CS_CONFIG = {
    // API 基础路径：同源部署，指向 /cs/... 路由
    apiBase: '/cs',
    // WebSocket 路径：由 Nginx 同源反代到 cs-ws 服务
    wsPath: '/cs/ws'
};

window.csApi = function (path, options) {
    options = options || {};
    var headers = options.headers || {};
    headers['Content-Type'] = 'application/json';
    var url = window.CS_CONFIG.apiBase + path;
    var init = { method: options.method || 'GET', headers: headers, credentials: 'same-origin' };
    if (options.body) init.body = JSON.stringify(options.body);
    return fetch(url, init).then(function (r) { return r.json(); });
};

/**
 * 实时连接（WebSocket）统一封装：两个页面共用，避免各写一套重连/心跳逻辑。
 *
 * 流程：POST 取一次性 ticket → 建立 WS → 首帧 auth → 收到 auth_ok 后开始心跳。
 * ticket 由服务端签发、60 秒有效且只能用一次，长期令牌不会出现在 WS 帧或 URL 中。
 *
 * @param {Object} opts
 * @param {string}   opts.ticketPath  取票接口路径（相对 apiBase）
 * @param {Function} opts.ticketBody  返回取票请求体
 * @param {Function} opts.onEvent     (eventName, data)
 * @param {Function} opts.onStatus    ('connecting'|'online'|'offline'|'denied')
 * @returns {{close: Function}}
 */
window.csConnect = function (opts) {
    var ws = null;
    var closed = false;
    var retry = 0;
    var retryTimer = null;
    var heartbeat = null;

    function status(s) { if (opts.onStatus) opts.onStatus(s); }

    function stopHeartbeat() {
        if (heartbeat) { clearInterval(heartbeat); heartbeat = null; }
    }

    function startHeartbeat() {
        stopHeartbeat();
        heartbeat = setInterval(function () {
            if (ws && ws.readyState === 1) ws.send('{"type":"ping"}');
        }, 25000);
    }

    function schedule() {
        if (closed) return;
        var delay = Math.min(1000 * Math.pow(2, retry), 15000);
        retry++;
        clearTimeout(retryTimer);
        retryTimer = setTimeout(connect, delay);
    }

    function connect() {
        if (closed) return;
        status('connecting');

        csApi(opts.ticketPath, { method: 'POST', body: opts.ticketBody ? opts.ticketBody() : {} })
            .then(function (r) {
                if (closed) return;
                if (r.code !== 0) {
                    // 401/403 表示凭据失效，重连没有意义
                    if (r.code === 401 || r.code === 403) { status('denied'); return; }
                    schedule();
                    return;
                }
                open(r.ticket);
            })
            .catch(function () { schedule(); });
    }

    function open(ticket) {
        var proto = location.protocol === 'https:' ? 'wss://' : 'ws://';
        try {
            ws = new WebSocket(proto + location.host + CS_CONFIG.wsPath);
        } catch (e) {
            schedule();
            return;
        }

        ws.onopen = function () {
            ws.send(JSON.stringify({ type: 'auth', ticket: ticket }));
        };

        ws.onmessage = function (ev) {
            var m;
            try { m = JSON.parse(ev.data); } catch (e) { return; }

            if (m.type === 'auth_ok') {
                retry = 0;
                status('online');
                startHeartbeat();
                return;
            }
            if (m.type === 'pong') return;
            if (m.type === 'error') {
                status('denied');
                try { ws.close(); } catch (e) {}
                return;
            }
            if (m.type === 'event' && opts.onEvent) {
                opts.onEvent(m.event, m.data || {});
            }
        };

        ws.onclose = function () {
            stopHeartbeat();
            if (!closed) { status('offline'); schedule(); }
        };

        ws.onerror = function () {
            try { ws.close(); } catch (e) {}
        };
    }

    connect();

    return {
        close: function () {
            closed = true;
            clearTimeout(retryTimer);
            stopHeartbeat();
            if (ws) { try { ws.close(); } catch (e) {} }
            ws = null;
        }
    };
};
