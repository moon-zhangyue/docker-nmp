{extend name="layout/app" /}

{block name="content"}
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Redis有序集合(ZSet)演示</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 基本操作 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">基本操作</h2>
            <p class="mb-4">有序集合基本操作示例，展示常用的有序集合命令。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('basic')">运行示例</button>
            </div>
        </div>
        
        <!-- 排行榜 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">排行榜应用</h2>
            <p class="mb-4">使用有序集合实现游戏排行榜，支持添加分数、查看排名等功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('leaderboard')">运行示例</button>
            </div>
        </div>
        
        <!-- 延迟队列 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">延迟队列</h2>
            <p class="mb-4">使用有序集合实现延迟队列，以时间戳为分数，实现定时任务功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('delayQueue')">运行示例</button>
            </div>
        </div>
        
        <!-- 权重搜索 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">权重搜索</h2>
            <p class="mb-4">使用有序集合实现权重搜索，为搜索结果分配权重，按权重排序。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('weightedSearch')">运行示例</button>
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
async function fetchData(action) {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const response = await fetch(`/redis/zset/${action}`);
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