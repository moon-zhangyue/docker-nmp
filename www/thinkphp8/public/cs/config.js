// 智能客服前端配置
window.CS_CONFIG = {
    // API 基础路径：同源部署，指向 /cs/... 路由
    apiBase: '/cs',
    // 管理后台接口令牌（与服务端 Config 中的值一致）
    adminToken: 'cs-admin-token'
};

window.csApi = function (path, options) {
    options = options || {};
    var headers = options.headers || {};
    headers['Content-Type'] = 'application/json';
    if (options.admin) headers['X-CS-Token'] = window.CS_CONFIG.adminToken;
    var url = window.CS_CONFIG.apiBase + path;
    var init = { method: options.method || 'GET', headers: headers };
    if (options.body) init.body = JSON.stringify(options.body);
    return fetch(url, init).then(function (r) { return r.json(); });
};
