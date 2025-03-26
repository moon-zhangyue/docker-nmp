import request from '@/utils/request'

// 获取所有主题
export function getTopics(params) {
  return request({
    url: '/kafka/topics',
    method: 'get',
    params
  })
}

// 创建主题
export function createTopic(data) {
  return request({
    url: '/kafka/topics/create',
    method: 'post',
    data
  })
}

// 删除主题
export function deleteTopic(data) {
  return request({
    url: '/kafka/topics/delete',
    method: 'post',
    data
  })
}

// 获取所有Broker
export function getBrokers(params) {
  return request({
    url: '/kafka/brokers',
    method: 'get',
    params
  })
}

// 获取消费者状态
export function getConsumers(params) {
  return request({
    url: '/monitoring/consumers',
    method: 'get',
    params
  })
} 