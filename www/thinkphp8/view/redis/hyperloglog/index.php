{extend name="layout/app" /}

{block name="content"}
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Redis基数统计(HyperLogLog)演示</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 基本操作 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">基本操作</h2>
            <p class="mb-4">HyperLogLog基本操作示例，展示添加、统计和合并等功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('basic')">运行示例</button>
            </div>
        </div>
        
        <!-- UV统计 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">UV统计</h2>
            <p class="mb-4">使用HyperLogLog实现网站UV(独立访客)统计，支持日、周、月度统计。</p>
            <div class="flex flex-wrap gap-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('uvStats', 'simulate')">模拟数据</button>
                <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('uvStats', 'stats')">查看统计</button>
            </div>
            <div class="mt-4">
                <form id="uvRecordForm" class="space-y-3">
                    <h3 class="text-lg font-medium">记录访问</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">用户ID (可选)</label>
                        <input type="text" id="visitorId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="不填则使用IP">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">页面</label>
                        <select id="page" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                            <option value="home">首页</option>
                            <option value="product">产品页</option>
                            <option value="about">关于我们</option>
                            <option value="contact">联系我们</option>
                        </select>
                    </div>
                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded w-full"
                        onclick="recordUV()">记录访问</button>
                </form>
            </div>
        </div>
        
        <!-- 搜索关键词统计 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">搜索关键词统计</h2>
            <p class="mb-4">使用HyperLogLog统计搜索关键词的独立用户数，分析热门搜索词。</p>
            <div class="flex flex-wrap gap-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('searchKeywords', 'stats')">模拟数据</button>
                <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('searchKeywords', 'popular_keywords')">热门关键词</button>
            </div>
            <div class="mt-4">
                <form id="searchKeywordForm" class="space-y-3">
                    <h3 class="text-lg font-medium">记录搜索</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">关键词</label>
                        <input type="text" id="keyword" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="输入搜索关键词">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">用户ID (可选)</label>
                        <input type="text" id="searchUserId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="不填则使用IP">
                    </div>
                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded w-full"
                        onclick="recordSearch()">记录搜索</button>
                </form>
            </div>
            <div class="mt-4">
                <form id="keywordStatsForm" class="space-y-3">
                    <h3 class="text-lg font-medium">查询关键词统计</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">关键词</label>
                        <input type="text" id="statsKeyword" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="输入要查询的关键词">
                    </div>
                    <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded w-full"
                        onclick="keywordStats()">查询统计</button>
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
async function fetchData(action, subAction = '') {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        let url = `/redis/hyperloglog/${action}`;
        if (subAction) {
            url += `?action=${subAction}`;
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

async function recordUV() {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const userId = document.getElementById('visitorId').value;
        const page = document.getElementById('page').value;
        
        let url = `/redis/hyperloglog/uvStats?action=record&page=${page}`;
        if (userId) {
            url += `&user_id=${encodeURIComponent(userId)}`;
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

async function recordSearch() {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const keyword = document.getElementById('keyword').value;
        const userId = document.getElementById('searchUserId').value;
        
        if (!keyword) {
            alert('请输入搜索关键词');
            return;
        }
        
        let url = `/redis/hyperloglog/searchKeywords?action=record&keyword=${encodeURIComponent(keyword)}`;
        if (userId) {
            url += `&user_id=${encodeURIComponent(userId)}`;
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

async function keywordStats() {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const keyword = document.getElementById('statsKeyword').value;
        
        if (!keyword) {
            alert('请输入要查询的关键词');
            return;
        }
        
        const url = `/redis/hyperloglog/searchKeywords?action=keyword_stats&keyword=${encodeURIComponent(keyword)}`;
        
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