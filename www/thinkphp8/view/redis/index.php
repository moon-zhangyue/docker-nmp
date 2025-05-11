<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redis 7.2 使用示例 - ThinkPHP 8</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }
        .demo-section {
            margin-bottom: 2rem;
        }
        pre {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.25rem;
        }
        .card-header {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <header class="py-3 mb-4 border-bottom">
        <div class="container d-flex flex-wrap justify-content-center">
            <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-database me-2" viewBox="0 0 16 16">
                    <path d="M4.318 2.687C5.234 2.271 6.536 2 8 2s2.766.27 3.682.687C12.644 3.125 13 3.627 13 4c0 .374-.356.875-1.318 1.313C10.766 5.729 9.464 6 8 6s-2.766-.27-3.682-.687C3.356 4.875 3 4.373 3 4c0-.374.356-.875 1.318-1.313ZM13 5.698V7c0 .374-.356.875-1.318 1.313C10.766 8.729 9.464 9 8 9s-2.766-.27-3.682-.687C3.356 7.875 3 7.373 3 7V5.698c.271.202.58.378.904.525C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777A4.92 4.92 0 0 0 13 5.698ZM14 4c0-1.007-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1s-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4v9c0 1.007.875 1.755 1.904 2.223C4.978 15.71 6.427 16 8 16s3.022-.289 4.096-.777C13.125 14.755 14 14.007 14 13V4Zm-1 4.698V10c0 .374-.356.875-1.318 1.313C10.766 11.729 9.464 12 8 12s-2.766-.27-3.682-.687C3.356 10.875 3 10.373 3 10V8.698c.271.202.58.378.904.525C4.978 9.71 6.427 10 8 10s3.022-.289 4.096-.777A4.92 4.92 0 0 0 13 8.698Zm0 3V13c0 .374-.356.875-1.318 1.313C10.766 14.729 9.464 15 8 15s-2.766-.27-3.682-.687C3.356 13.875 3 13.373 3 13v-1.302c.271.202.58.378.904.525C4.978 12.71 6.427 13 8 13s3.022-.289 4.096-.777c.324-.147.633-.323.904-.525Z"/>
                </svg>
                <span class="fs-4">Redis 7.2 演示</span>
            </a>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="p-5 mb-4 bg-light rounded-3">
                    <div class="container-fluid py-3">
                        <h1 class="display-5 fw-bold">Redis 7.2 使用示例</h1>
                        <p class="col-md-8 fs-4">本演示展示了基于 ThinkPHP 8 框架的 Redis 7.2 各种数据类型的使用场景，包括缓存穿透和雪崩防护方案。</p>
                        <button class="btn btn-primary btn-lg" id="checkConnection">检查 Redis 连接</button>
                        <button class="btn btn-danger btn-lg" id="flushDB">清空当前库</button>
                        <div class="mt-3" id="connectionResult"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Redis 数据类型演示</h2>
                <p>Redis 支持多种数据类型，每种类型适用于不同的场景。以下是各种数据类型的演示：</p>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">String 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">字符串</h5>
                                <p class="card-text">适用于缓存对象、计数器、分布式锁等场景。</p>
                                <a href="/redis/string" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">Hash 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">哈希表</h5>
                                <p class="card-text">适用于存储对象、用户信息、购物车等场景。</p>
                                <a href="/redis/hash" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">List 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">列表</h5>
                                <p class="card-text">适用于消息队列、最新动态、排行榜等场景。</p>
                                <a href="/redis/list" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">Set 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">集合</h5>
                                <p class="card-text">适用于标签、共同好友、去重等场景。</p>
                                <a href="/redis/set" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">Sorted Set 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">有序集合</h5>
                                <p class="card-text">适用于排行榜、权重排序、延迟队列等场景。</p>
                                <a href="/redis/zset" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">Geo 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">地理位置</h5>
                                <p class="card-text">适用于附近的人、地理位置查询等场景。</p>
                                <a href="/redis/geo" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">BitMap 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">位图</h5>
                                <p class="card-text">适用于用户签到、在线状态、布隆过滤器等场景。</p>
                                <a href="/redis/bitmap" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">HyperLogLog 类型</div>
                            <div class="card-body">
                                <h5 class="card-title">基数统计</h5>
                                <p class="card-text">适用于UV统计、访问量计数等场景。</p>
                                <a href="/redis/hyperloglog" class="btn btn-primary">查看演示</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">防止缓存穿透</div>
                    <div class="card-body">
                        <h5 class="card-title">缓存穿透防护</h5>
                        <p class="card-text">缓存穿透是指查询一个不存在的数据，由于缓存不命中，会导致请求直接落到数据库上。</p>
                        <ul>
                            <li>空值缓存：对不存在的数据也进行缓存，并设置较短的过期时间</li>
                            <li>布隆过滤器：使用布隆过滤器快速判断数据是否存在</li>
                        </ul>
                        <a href="/redis/string/preventCachePenetration" class="btn btn-primary">查看演示</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">防止缓存雪崩</div>
                    <div class="card-body">
                        <h5 class="card-title">缓存雪崩防护</h5>
                        <p class="card-text">缓存雪崩是指在某一时刻，大量的缓存同时失效，导致所有请求都落到数据库上。</p>
                        <ul>
                            <li>过期时间随机化：为缓存设置随机的过期时间，避免同时过期</li>
                            <li>加锁或队列：对请求进行限流和排队</li>
                            <li>多级缓存：设置多级缓存提高系统可用性</li>
                        </ul>
                        <a href="/redis/string/preventCacheAvalanche" class="btn btn-primary">查看演示</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Redis 与数据库配合使用</div>
                    <div class="card-body">
                        <h5 class="card-title">缓存策略</h5>
                        <p class="card-text">Redis 作为缓存与数据库配合使用的常见策略：</p>
                        <ul>
                            <li><strong>Cache Aside（旁路缓存）</strong>：先查缓存，不存在则查库，然后更新缓存</li>
                            <li><strong>Read Through（读穿透）</strong>：应用只和缓存交互，缓存负责和数据库交互</li>
                            <li><strong>Write Through（写穿透）</strong>：先更新缓存，缓存同步更新数据库</li>
                            <li><strong>Write Back（写回）</strong>：先更新缓存，定期批量更新数据库</li>
                        </ul>
                        <p>本示例中的 <code>remember</code> 方法实现了 Cache Aside 策略，推荐作为主要缓存策略使用。</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-3 my-4">
        <div class="container">
            <p class="text-center text-muted">Redis 7.2 使用示例 &copy; <?= date('Y') ?> ThinkPHP 8</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('checkConnection').addEventListener('click', function() {
                fetch('/redis-demo/checkConnection')
                    .then(response => response.json())
                    .then(data => {
                        const resultDiv = document.getElementById('connectionResult');
                        if (data.code === 0) {
                            resultDiv.innerHTML = `
                                <div class="alert alert-success">
                                    ${data.msg}
                                    <pre>${JSON.stringify(data.data, null, 2)}</pre>
                                </div>
                            `;
                        } else {
                            resultDiv.innerHTML = `
                                <div class="alert alert-danger">
                                    ${data.msg}
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        document.getElementById('connectionResult').innerHTML = `
                            <div class="alert alert-danger">
                                请求失败: ${error}
                            </div>
                        `;
                    });
            });
            
            document.getElementById('flushDB').addEventListener('click', function() {
                if (confirm('确定要清空当前Redis库吗？此操作不可恢复！')) {
                    fetch('/redis-demo/flushDB')
                        .then(response => response.json())
                        .then(data => {
                            const resultDiv = document.getElementById('connectionResult');
                            if (data.code === 0) {
                                resultDiv.innerHTML = `
                                    <div class="alert alert-success">
                                        ${data.msg}
                                    </div>
                                `;
                            } else {
                                resultDiv.innerHTML = `
                                    <div class="alert alert-danger">
                                        ${data.msg}
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            document.getElementById('connectionResult').innerHTML = `
                                <div class="alert alert-danger">
                                    请求失败: ${error}
                                </div>
                            `;
                        });
                }
            });
        });
    </script>
</body>
</html> 