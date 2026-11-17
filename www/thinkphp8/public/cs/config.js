// 智能客服前端配置
window.CS_CONFIG = {
    // API 基础路径：同源部署，指向 /cs/... 路由
    apiBase: '/cs'
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
