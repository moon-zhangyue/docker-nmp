<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    // 全局中间件：保持为空。
    // 鉴权不再全局拦截，改为在 config/route.php 的路由组上显式挂载 AuthCheck，
    // 避免「默认拦截 + 白名单」模式下新增路由忘记加白名单就被误拦。
    ''    => [
    ],
    // api应用中间件(应用中间件仅在多应用模式下有效)
    'api' => [
    ]
];