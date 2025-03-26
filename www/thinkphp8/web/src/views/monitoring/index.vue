<template>
  <div class="monitoring-container">
    <el-row :gutter="20">
      <!-- 系统资源使用情况 -->
      <el-col :span="8">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>系统资源</span>
            </div>
          </template>
          <div class="resource-item">
            <div class="label">CPU使用率</div>
            <el-progress 
              :percentage="cpuUsage" 
              :format="format"
              :status="cpuStatus"
            />
          </div>
          <div class="resource-item">
            <div class="label">内存使用率</div>
            <el-progress 
              :percentage="memoryUsage" 
              :format="format"
              :status="memoryStatus"
            />
          </div>
          <div class="resource-item">
            <div class="label">磁盘使用率</div>
            <el-progress 
              :percentage="diskUsage" 
              :format="format"
              :status="diskStatus"
            />
          </div>
        </el-card>
      </el-col>

      <!-- Kafka状态 -->
      <el-col :span="8">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>Kafka状态</span>
            </div>
          </template>
          <div class="status-item">
            <div class="label">Broker数量</div>
            <div class="value">{{ brokerCount }}</div>
          </div>
          <div class="status-item">
            <div class="label">主题数量</div>
            <div class="value">{{ topicCount }}</div>
          </div>
          <div class="status-item">
            <div class="label">消费者组数量</div>
            <div class="value">{{ consumerGroupCount }}</div>
          </div>
        </el-card>
      </el-col>

      <!-- 队列状态 -->
      <el-col :span="8">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>队列状态</span>
            </div>
          </template>
          <div class="queue-item" v-for="(queue, name) in queueStatus" :key="name">
            <div class="queue-name">{{ name }}</div>
            <div class="queue-stats">
              <el-tag size="small" type="info">等待: {{ queue.waiting }}</el-tag>
              <el-tag size="small" type="warning">处理中: {{ queue.processing }}</el-tag>
              <el-tag size="small" type="danger">失败: {{ queue.failed }}</el-tag>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 消费者状态 -->
    <el-card class="mt-20">
      <template #header>
        <div class="card-header">
          <span>消费者状态</span>
        </div>
      </template>
      <el-table :data="consumers" v-loading="loading">
        <el-table-column prop="group_id" label="消费者组" />
        <el-table-column prop="state" label="状态">
          <template #default="{ row }">
            <el-tag :type="getConsumerStateType(row.state)">
              {{ row.state }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="member_count" label="成员数" width="100" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleViewConsumers(row)">
              查看详情
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { getConsumers } from '@/api/kafka'

const loading = ref(false)
const consumers = ref([])
const metrics = ref({
  cpu: { load_avg: { '1min': 0, '5min': 0, '15min': 0 } },
  memory: { php: { current: '0 B', peak: '0 B' } },
  disk: { used_percent: '0%' }
})
const queueStatus = ref({})
const brokerCount = ref(0)
const topicCount = ref(0)
const consumerGroupCount = ref(0)

// 计算使用率
const cpuUsage = computed(() => {
  return Math.round(metrics.value.cpu.load_avg['1min'] * 100)
})

const memoryUsage = computed(() => {
  const current = parseInt(metrics.value.memory.php.current)
  const peak = parseInt(metrics.value.memory.php.peak)
  return Math.round((current / peak) * 100)
})

const diskUsage = computed(() => {
  return parseInt(metrics.value.disk.used_percent)
})

// 状态类型
const cpuStatus = computed(() => {
  return cpuUsage.value > 80 ? 'exception' : cpuUsage.value > 60 ? 'warning' : 'success'
})

const memoryStatus = computed(() => {
  return memoryUsage.value > 80 ? 'exception' : memoryUsage.value > 60 ? 'warning' : 'success'
})

const diskStatus = computed(() => {
  return diskUsage.value > 80 ? 'exception' : diskUsage.value > 60 ? 'warning' : 'success'
})

// 格式化进度条
const format = (percentage) => {
  return percentage + '%'
}

// 获取消费者状态类型
const getConsumerStateType = (state) => {
  const types = {
    'Stable': 'success',
    'Empty': 'info',
    'Dead': 'danger',
    'PreparingRebalance': 'warning',
    'CompletingRebalance': 'warning'
  }
  return types[state] || 'info'
}

// 获取消费者列表
const fetchConsumers = async () => {
  loading.value = true
  try {
    const res = await getConsumers()
    consumers.value = res.consumers
    consumerGroupCount.value = res.total
  } catch (error) {
    console.error('获取消费者状态失败:', error)
  } finally {
    loading.value = false
  }
}

// 查看消费者详情
const handleViewConsumers = (row) => {
  // TODO: 实现查看消费者详情功能
}

// 定时刷新数据
let timer = null

onMounted(() => {
  fetchConsumers()
  timer = setInterval(fetchConsumers, 30000) // 每30秒刷新一次
})

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
  }
})
</script>

<style scoped>
.monitoring-container {
  padding: 20px;
}

.mt-20 {
  margin-top: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.resource-item,
.status-item {
  margin-bottom: 20px;
}

.label {
  margin-bottom: 8px;
  color: #606266;
}

.value {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.queue-item {
  margin-bottom: 15px;
}

.queue-name {
  margin-bottom: 8px;
  font-weight: bold;
}

.queue-stats {
  display: flex;
  gap: 8px;
}
</style> 