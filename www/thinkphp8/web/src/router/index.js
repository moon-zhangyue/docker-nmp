import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    component: () => import('@/layouts/DefaultLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/topics'
      },
      {
        path: 'topics',
        name: 'Topics',
        component: () => import('@/views/topics/index.vue'),
        meta: {
          title: '主题管理',
          requiresAuth: true
        }
      },
      {
        path: 'monitoring',
        name: 'Monitoring',
        component: () => import('@/views/monitoring/index.vue'),
        meta: {
          title: '系统监控',
          requiresAuth: true
        }
      }
    ]
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: {
      title: '登录'
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  // 设置页面标题
  document.title = to.meta.title ? `${to.meta.title} - Kafka管理系统` : 'Kafka管理系统'
  
  // 检查是否需要认证
  if (to.meta.requiresAuth) {
    const token = localStorage.getItem('token')
    if (!token) {
      next('/login')
    } else {
      next()
    }
  } else {
    next()
  }
})

export default router 