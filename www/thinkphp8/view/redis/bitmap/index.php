{extend name="layout/app" /}

{block name="content"}
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Redis位图(BitMap)演示</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 基本操作 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">基本操作</h2>
            <p class="mb-4">位图基本操作示例，展示位操作和位统计功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('basic')">运行示例</button>
            </div>
        </div>
        
        <!-- 用户签到 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">用户签到</h2>
            <p class="mb-4">使用位图实现用户签到功能，高效存储和统计签到记录。</p>
            <div class="flex flex-wrap gap-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('userSign', 'status')">签到状态</button>
                <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('userSign', 'sign')">执行签到</button>
                <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('userSign', 'month_stats')">月度统计</button>
            </div>
            <div class="mt-4">
                <form id="signForm" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">用户ID</label>
                        <input type="number" id="userId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="1" min="1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">日期 (yyyy-mm-dd)</label>
                        <input type="date" id="signDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                </form>
            </div>
        </div>
        
        <!-- 在线状态 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">在线状态</h2>
            <p class="mb-4">使用位图实现用户在线状态管理，可同时记录和统计大量用户状态。</p>
            <div class="flex flex-wrap gap-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('onlineStatus', 'status')">状态概览</button>
                <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded" 
                    onclick="userOnlineAction('login')">用户登录</button>
                <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" 
                    onclick="userOnlineAction('logout')">用户登出</button>
                <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded" 
                    onclick="userOnlineAction('check')">检查在线</button>
            </div>
            <div class="mt-4">
                <form id="onlineForm" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">用户ID</label>
                        <input type="number" id="onlineUserId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="1" min="1">
                    </div>
                </form>
            </div>
        </div>
        
        <!-- 布隆过滤器 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">布隆过滤器</h2>
            <p class="mb-4">使用位图实现布隆过滤器，用于快速判断元素是否存在于集合中。</p>
            <div class="flex flex-wrap gap-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('bloomFilter', 'test')">运行测试</button>
                <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded" 
                    onclick="bloomFilterAction('add')">添加元素</button>
                <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded" 
                    onclick="bloomFilterAction('check')">检查元素</button>
            </div>
            <div class="mt-4">
                <form id="bloomForm" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">元素值</label>
                        <input type="text" id="bloomValue" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="输入要添加或检查的值">
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 结果区域 -->
    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4">执行结果</h2>
        <div id="result" class="bg-gray-100 rounded-lg p-4 min-h-[200px]">
            <p class="text-gray-500">点击上方按钮运行示例...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 设置当前日期为默认值
    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0];
    document.getElementById('signDate').value = formattedDate;
});

async function fetchData(action, subAction = '') {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        let url = `/redis/bitmap/${action}`;
        let params = [];
        
        if (subAction) {
            params.push(`action=${subAction}`);
        }
        
        if (action === 'userSign') {
            const userId = document.getElementById('userId').value;
            const date = document.getElementById('signDate').value;
            
            if (userId) params.push(`user_id=${userId}`);
            if (date) params.push(`date=${date}`);
            
            if (subAction === 'month_stats') {
                // 只保留年月
                const yearMonth = date.substring(0, 7);
                params.push(`year_month=${yearMonth}`);
            }
        }
        
        if (params.length > 0) {
            url += `?${params.join('&')}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        let resultHtml = '';
        
        if (data.code === 0) {
            resultHtml = `<div class="text-green-600 font-semibold mb-2">${data.msg}</div>`;
            
            if (data.data) {
                resultHtml += '<div class="overflow-auto max-h-[600px]">';
                resultHtml += '<pre class="text-sm">' + syntaxHighlight(data.data) + '</pre>';
                resultHtml += '</div>';
            }
        } else {
            resultHtml = `<div class="text-red-600 font-semibold mb-2">错误：${data.msg}</div>`;
        }
        
        document.getElementById('result').innerHTML = resultHtml;
    } catch (error) {
        document.getElementById('result').innerHTML = `<div class="text-red-600 font-semibold mb-2">请求出错：${error.message}</div>`;
    }
}

async function userOnlineAction(action) {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const userId = document.getElementById('onlineUserId').value;
        
        if (!userId) {
            alert('请输入用户ID');
            return;
        }
        
        const url = `/redis/bitmap/onlineStatus?action=${action}&user_id=${userId}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        let resultHtml = '';
        
        if (data.code === 0) {
            resultHtml = `<div class="text-green-600 font-semibold mb-2">${data.msg}</div>`;
            
            if (data.data) {
                resultHtml += '<div class="overflow-auto max-h-[600px]">';
                resultHtml += '<pre class="text-sm">' + syntaxHighlight(data.data) + '</pre>';
                resultHtml += '</div>';
            }
        } else {
            resultHtml = `<div class="text-red-600 font-semibold mb-2">错误：${data.msg}</div>`;
        }
        
        document.getElementById('result').innerHTML = resultHtml;
    } catch (error) {
        document.getElementById('result').innerHTML = `<div class="text-red-600 font-semibold mb-2">请求出错：${error.message}</div>`;
    }
}

async function bloomFilterAction(action) {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const value = document.getElementById('bloomValue').value;
        
        if (!value) {
            alert('请输入元素值');
            return;
        }
        
        const url = `/redis/bitmap/bloomFilter?action=${action}&value=${encodeURIComponent(value)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        let resultHtml = '';
        
        if (data.code === 0) {
            resultHtml = `<div class="text-green-600 font-semibold mb-2">${data.msg}</div>`;
            
            if (data.data) {
                resultHtml += '<div class="overflow-auto max-h-[600px]">';
                resultHtml += '<pre class="text-sm">' + syntaxHighlight(data.data) + '</pre>';
                resultHtml += '</div>';
            }
        } else {
            resultHtml = `<div class="text-red-600 font-semibold mb-2">错误：${data.msg}</div>`;
        }
        
        document.getElementById('result').innerHTML = resultHtml;
    } catch (error) {
        document.getElementById('result').innerHTML = `<div class="text-red-600 font-semibold mb-2">请求出错：${error.message}</div>`;
    }
}

function syntaxHighlight(json) {
    if (typeof json !== 'string') {
        json = JSON.stringify(json, null, 2);
    }
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'text-purple-600';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'text-red-600';
            } else {
                cls = 'text-green-600';
            }
        } else if (/true|false/.test(match)) {
            cls = 'text-blue-600';
        } else if (/null/.test(match)) {
            cls = 'text-gray-600';
        } else {
            cls = 'text-yellow-600';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}
</script>
{/block} 