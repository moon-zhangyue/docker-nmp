{extend name="layout/app" /}

{block name="content"}
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Redis地理位置(Geo)演示</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 基本操作 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">基本操作</h2>
            <p class="mb-4">地理位置基本操作示例，展示添加位置、计算距离等功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('basic')">运行示例</button>
            </div>
        </div>
        
        <!-- 附近的人 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">附近的人</h2>
            <p class="mb-4">使用地理位置功能实现"附近的人"功能，查找指定范围内的用户。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('nearbyUsers', 'list')">查看用户列表</button>
            </div>
            <div class="mt-4">
                <form id="nearbyForm" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">搜索方式</label>
                        <select id="nearbySearchType" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                            <option value="id">按用户ID搜索</option>
                            <option value="location">按位置搜索</option>
                        </select>
                    </div>
                    
                    <div id="userIdSearchFields">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">用户ID</label>
                            <input type="number" id="userId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" min="1">
                        </div>
                    </div>
                    
                    <div id="locationSearchFields" class="hidden">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">经度</label>
                            <input type="number" id="longitude" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.000001">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">纬度</label>
                            <input type="number" id="latitude" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.000001">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">搜索半径 (公里)</label>
                        <input type="number" id="radius" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="5" min="0.1" step="0.1">
                    </div>
                    
                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded w-full"
                        onclick="searchNearby()">搜索附近用户</button>
                </form>
            </div>
        </div>
        
        <!-- 店铺查找 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">店铺查找</h2>
            <p class="mb-4">使用地理位置功能实现附近店铺查找功能。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('storeLocator', 'list')">查看店铺列表</button>
            </div>
            <div class="mt-4">
                <form id="storeSearchForm" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">经度</label>
                        <input type="number" id="storeLongitude" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.000001" value="116.40">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">纬度</label>
                        <input type="number" id="storeLatitude" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.000001" value="39.91">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">搜索半径 (公里)</label>
                        <input type="number" id="storeRadius" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="5" min="0.1" step="0.1">
                    </div>
                    
                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded w-full"
                        onclick="searchStores()">搜索附近店铺</button>
                </form>
            </div>
        </div>
        
        <!-- 路径规划 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">路径规划</h2>
            <p class="mb-4">使用地理位置功能实现简单的路径规划，查找途径的兴趣点。</p>
            <div class="flex justify-between items-center">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" 
                    onclick="fetchData('routePlanning')">运行示例</button>
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
document.getElementById('nearbySearchType').addEventListener('change', function() {
    const searchType = this.value;
    if (searchType === 'id') {
        document.getElementById('userIdSearchFields').classList.remove('hidden');
        document.getElementById('locationSearchFields').classList.add('hidden');
    } else {
        document.getElementById('userIdSearchFields').classList.add('hidden');
        document.getElementById('locationSearchFields').classList.remove('hidden');
    }
});

async function fetchData(action, subAction = '') {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        let url = `/redis/geo/${action}`;
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

async function searchNearby() {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const searchType = document.getElementById('nearbySearchType').value;
        const radius = document.getElementById('radius').value;
        
        let url = `/redis/geo/nearbyUsers?action=nearby&radius=${radius}`;
        
        if (searchType === 'id') {
            const userId = document.getElementById('userId').value;
            if (!userId) {
                alert('请输入用户ID');
                return;
            }
            url += `&user_id=${userId}`;
        } else {
            const longitude = document.getElementById('longitude').value;
            const latitude = document.getElementById('latitude').value;
            if (!longitude || !latitude) {
                alert('请输入经纬度坐标');
                return;
            }
            url += `&longitude=${longitude}&latitude=${latitude}`;
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

async function searchStores() {
    try {
        document.getElementById('result').innerHTML = '<p class="text-gray-500">加载中...</p>';
        
        const longitude = document.getElementById('storeLongitude').value;
        const latitude = document.getElementById('storeLatitude').value;
        const radius = document.getElementById('storeRadius').value;
        
        if (!longitude || !latitude) {
            alert('请输入经纬度坐标');
            return;
        }
        
        const url = `/redis/geo/storeLocator?action=search&longitude=${longitude}&latitude=${latitude}&radius=${radius}`;
        
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