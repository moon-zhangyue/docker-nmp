<template>
  <el-container class="layout-container">
    <el-aside width="200px">
      <el-menu
        :router="true"
        :default-active="route.path"
        class="el-menu-vertical"
      >
        <el-menu-item index="/topics">
          <el-icon><Document /></el-icon>
          <span>主题管理</span>
        </el-menu-item>
        <el-menu-item index="/monitoring">
          <el-icon><Monitor /></el-icon>
          <span>系统监控</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    
    <el-container>
      <el-header>
        <div class="header-content">
          <h2>Kafka管理系统</h2>
          <div class="user-info">
            <el-dropdown @command="handleCommand">
              <span class="el-dropdown-link">
                {{ username }}
                <el-icon class="el-icon--right"><arrow-down /></el-icon>
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="logout">退出登录</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>
      </el-header>
      
      <el-main>
        <router-view></router-view>
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Document, Monitor, ArrowDown } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const username = ref(localStorage.getItem('username') || '管理员')

const handleCommand = (command) => {
  if (command === 'logout') {
    localStorage.removeItem('token')
    localStorage.removeItem('username')
    router.push('/login')
  }
}
</script>

<style scoped>
.layout-container {
  height: 100vh;
}

.el-aside {
  background-color: #304156;
}

.el-menu {
  border-right: none;
}

.el-menu-vertical {
  height: 100%;
  background-color: #304156;
}

.el-menu-vertical:not(.el-menu--collapse) {
  width: 200px;
}

.el-header {
  background-color: #fff;
  border-bottom: 1px solid #e6e6e6;
  padding: 0 20px;
}

.header-content {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-content h2 {
  margin: 0;
  color: #303133;
}

.user-info {
  display: flex;
  align-items: center;
}

.el-dropdown-link {
  cursor: pointer;
  color: #409EFF;
}

.el-main {
  background-color: #f0f2f5;
  padding: 20px;
}
</style> 