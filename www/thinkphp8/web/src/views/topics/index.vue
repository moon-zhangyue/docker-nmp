<template>
  <div class="topics-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>主题管理</span>
          <el-button type="primary" @click="handleCreate">创建主题</el-button>
        </div>
      </template>
      
      <el-table :data="topics" v-loading="loading">
        <el-table-column prop="name" label="主题名称" />
        <el-table-column prop="partition_count" label="分区数" width="100" />
        <el-table-column label="操作" width="200">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleView(row)">查看</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 创建主题对话框 -->
    <el-dialog v-model="createDialogVisible" title="创建主题" width="500px">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="主题名称" prop="topic">
          <el-input v-model="createForm.topic" />
        </el-form-item>
        <el-form-item label="分区数" prop="partitions">
          <el-input-number v-model="createForm.partitions" :min="1" />
        </el-form-item>
        <el-form-item label="副本因子" prop="replication_factor">
          <el-input-number v-model="createForm.replication_factor" :min="1" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitCreate">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getTopics, createTopic, deleteTopic } from '@/api/kafka'

const loading = ref(false)
const topics = ref([])
const createDialogVisible = ref(false)
const createFormRef = ref(null)

const createForm = ref({
  topic: '',
  partitions: 1,
  replication_factor: 1
})

const createRules = {
  topic: [
    { required: true, message: '请输入主题名称', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_-]+$/, message: '主题名称只能包含字母、数字、下划线和破折号', trigger: 'blur' }
  ],
  partitions: [
    { required: true, message: '请输入分区数', trigger: 'blur' }
  ],
  replication_factor: [
    { required: true, message: '请输入副本因子', trigger: 'blur' }
  ]
}

// 获取主题列表
const fetchTopics = async () => {
  loading.value = true
  try {
    const res = await getTopics()
    topics.value = res.topics
  } catch (error) {
    console.error('获取主题列表失败:', error)
  } finally {
    loading.value = false
  }
}

// 创建主题
const handleCreate = () => {
  createDialogVisible.value = true
  createForm.value = {
    topic: '',
    partitions: 1,
    replication_factor: 1
  }
}

const submitCreate = async () => {
  if (!createFormRef.value) return
  
  await createFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await createTopic(createForm.value)
        ElMessage.success('创建主题成功')
        createDialogVisible.value = false
        fetchTopics()
      } catch (error) {
        console.error('创建主题失败:', error)
      }
    }
  })
}

// 删除主题
const handleDelete = (row) => {
  ElMessageBox.confirm('确定要删除该主题吗？', '提示', {
    type: 'warning'
  }).then(async () => {
    try {
      await deleteTopic({ topic: row.name })
      ElMessage.success('删除主题成功')
      fetchTopics()
    } catch (error) {
      console.error('删除主题失败:', error)
    }
  })
}

// 查看主题详情
const handleView = (row) => {
  // TODO: 实现查看主题详情功能
}

onMounted(() => {
  fetchTopics()
})
</script>

<style scoped>
.topics-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style> 