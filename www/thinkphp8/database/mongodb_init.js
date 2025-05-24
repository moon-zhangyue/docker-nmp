// MongoDB初始化脚本
// 用于创建ThinkPHP8 MongoDB项目所需的集合、索引和分片配置

// 切换到目标数据库
use thinkphp_mongodb;

print('开始初始化MongoDB数据库结构...');

// ================================
// 1. 产品目录集合 (文档存储特性)
// ================================
print('\n创建产品目录集合...');

// 创建products集合
db.createCollection('products', {
    validator: {
        $jsonSchema: {
            bsonType: 'object',
            required: ['name', 'sku', 'price', 'category_id'],
            properties: {
                name: {
                    bsonType: 'string',
                    description: '产品名称必须是字符串'
                },
                sku: {
                    bsonType: 'string',
                    description: 'SKU必须是字符串'
                },
                price: {
                    bsonType: 'number',
                    minimum: 0,
                    description: '价格必须是非负数'
                },
                category_id: {
                    bsonType: 'string',
                    description: '分类ID必须是字符串'
                },
                description: {
                    bsonType: 'string'
                },
                attributes: {
                    bsonType: 'object'
                },
                variants: {
                    bsonType: 'array'
                },
                tags: {
                    bsonType: 'array'
                },
                status: {
                    enum: ['active', 'inactive', 'discontinued'],
                    description: '状态必须是指定值之一'
                }
            }
        }
    }
});

// 创建产品集合索引
db.products.createIndex({ 'name': 'text', 'description': 'text' }, { name: 'text_search_index' });
db.products.createIndex({ 'sku': 1 }, { unique: true, name: 'sku_unique_index' });
db.products.createIndex({ 'category_id': 1 }, { name: 'category_index' });
db.products.createIndex({ 'price': 1 }, { name: 'price_index' });
db.products.createIndex({ 'created_at': -1 }, { name: 'created_time_index' });
db.products.createIndex({ 'status': 1 }, { name: 'status_index' });
db.products.createIndex({ 'tags': 1 }, { name: 'tags_index' });

print('产品目录集合创建完成');

// ================================
// 2. IoT数据集合 (分片特性)
// ================================
print('\n创建IoT数据集合...');

// 创建iot_data集合
db.createCollection('iot_data', {
    validator: {
        $jsonSchema: {
            bsonType: 'object',
            required: ['device_id', 'timestamp', 'data'],
            properties: {
                device_id: {
                    bsonType: 'string',
                    description: '设备ID必须是字符串'
                },
                timestamp: {
                    bsonType: 'date',
                    description: '时间戳必须是日期类型'
                },
                device_type: {
                    bsonType: 'string'
                },
                data: {
                    bsonType: 'object',
                    description: '数据必须是对象类型'
                },
                location: {
                    bsonType: 'object',
                    properties: {
                        type: {
                            enum: ['Point'],
                            description: '位置类型必须是Point'
                        },
                        coordinates: {
                            bsonType: 'array',
                            minItems: 2,
                            maxItems: 2,
                            items: {
                                bsonType: 'number'
                            }
                        }
                    }
                }
            }
        }
    }
});

// 创建IoT数据集合索引
db.iot_data.createIndex({ 'device_id': 1, 'timestamp': -1 }, { name: 'device_time_index' });
db.iot_data.createIndex({ 'timestamp': -1 }, { name: 'timestamp_index' });
db.iot_data.createIndex({ 'device_type': 1 }, { name: 'device_type_index' });
db.iot_data.createIndex({ 'location': '2dsphere' }, { name: 'location_geo_index' });
db.iot_data.createIndex({ 'data.temperature': 1 }, { name: 'temperature_index', sparse: true });
db.iot_data.createIndex({ 'data.humidity': 1 }, { name: 'humidity_index', sparse: true });

print('IoT数据集合创建完成');

// ================================
// 3. 位置数据集合 (地理空间特性)
// ================================
print('\n创建位置数据集合...');

// 创建locations集合
db.createCollection('locations', {
    validator: {
        $jsonSchema: {
            bsonType: 'object',
            required: ['name', 'location', 'type'],
            properties: {
                name: {
                    bsonType: 'string',
                    description: '位置名称必须是字符串'
                },
                type: {
                    enum: ['poi', 'store', 'warehouse', 'delivery_point'],
                    description: '位置类型必须是指定值之一'
                },
                location: {
                    bsonType: 'object',
                    required: ['type', 'coordinates'],
                    properties: {
                        type: {
                            enum: ['Point'],
                            description: '几何类型必须是Point'
                        },
                        coordinates: {
                            bsonType: 'array',
                            minItems: 2,
                            maxItems: 2,
                            items: {
                                bsonType: 'number'
                            },
                            description: '坐标必须是[经度, 纬度]格式'
                        }
                    }
                },
                address: {
                    bsonType: 'string'
                },
                properties: {
                    bsonType: 'object'
                }
            }
        }
    }
});

// 创建位置集合索引
db.locations.createIndex({ 'location': '2dsphere' }, { name: 'location_2dsphere_index' });
db.locations.createIndex({ 'type': 1 }, { name: 'location_type_index' });
db.locations.createIndex({ 'name': 1 }, { name: 'location_name_index' });
db.locations.createIndex({ 'name': 'text', 'address': 'text' }, { name: 'location_text_index' });

print('位置数据集合创建完成');

// ================================
// 4. 分析数据集合 (聚合框架特性)
// ================================
print('\n创建分析数据集合...');

// 创建analytics集合
db.createCollection('analytics', {
    validator: {
        $jsonSchema: {
            bsonType: 'object',
            required: ['user_id', 'event_type', 'timestamp'],
            properties: {
                user_id: {
                    bsonType: 'string',
                    description: '用户ID必须是字符串'
                },
                event_type: {
                    bsonType: 'string',
                    description: '事件类型必须是字符串'
                },
                timestamp: {
                    bsonType: 'date',
                    description: '时间戳必须是日期类型'
                },
                session_id: {
                    bsonType: 'string'
                },
                properties: {
                    bsonType: 'object'
                },
                page_url: {
                    bsonType: 'string'
                },
                user_agent: {
                    bsonType: 'string'
                },
                ip_address: {
                    bsonType: 'string'
                }
            }
        }
    }
});

// 创建分析集合索引
db.analytics.createIndex({ 'user_id': 1, 'timestamp': -1 }, { name: 'user_time_index' });
db.analytics.createIndex({ 'event_type': 1 }, { name: 'event_type_index' });
db.analytics.createIndex({ 'timestamp': -1 }, { name: 'analytics_timestamp_index' });
db.analytics.createIndex({ 'session_id': 1 }, { name: 'session_index' });
db.analytics.createIndex({ 'user_id': 1, 'event_type': 1 }, { name: 'user_event_index' });

print('分析数据集合创建完成');

// ================================
// 5. 全球数据集合 (副本集特性)
// ================================
print('\n创建全球数据集合...');

// 创建global_data集合
db.createCollection('global_data', {
    validator: {
        $jsonSchema: {
            bsonType: 'object',
            required: ['global_id', 'region', 'data_type'],
            properties: {
                global_id: {
                    bsonType: 'string',
                    description: '全球ID必须是字符串'
                },
                region: {
                    enum: ['us-east', 'us-west', 'eu-central', 'asia-pacific'],
                    description: '区域必须是指定值之一'
                },
                data_type: {
                    bsonType: 'string',
                    description: '数据类型必须是字符串'
                },
                data: {
                    bsonType: 'object'
                },
                sync_status: {
                    enum: ['pending', 'synced', 'failed'],
                    description: '同步状态必须是指定值之一'
                },
                last_sync_time: {
                    bsonType: 'date'
                },
                version: {
                    bsonType: 'int',
                    minimum: 1
                }
            }
        }
    }
});

// 创建全球数据集合索引
db.global_data.createIndex({ 'global_id': 1 }, { unique: true, name: 'global_id_unique_index' });
db.global_data.createIndex({ 'region': 1 }, { name: 'region_index' });
db.global_data.createIndex({ 'data_type': 1 }, { name: 'data_type_index' });
db.global_data.createIndex({ 'created_at': -1 }, { name: 'global_created_time_index' });
db.global_data.createIndex({ 'sync_status': 1 }, { name: 'sync_status_index' });
db.global_data.createIndex({ 'region': 1, 'data_type': 1 }, { name: 'region_data_type_index' });

print('全球数据集合创建完成');

// ================================
// 6. 创建视图 (聚合框架特性)
// ================================
print('\n创建聚合视图...');

// 创建用户行为摘要视图
db.createView('user_behavior_summary', 'analytics', [
    {
        $group: {
            _id: {
                user_id: '$user_id',
                date: { $dateToString: { format: '%Y-%m-%d', date: '$timestamp' } }
            },
            total_events: { $sum: 1 },
            event_types: { $addToSet: '$event_type' },
            first_event: { $min: '$timestamp' },
            last_event: { $max: '$timestamp' },
            unique_sessions: { $addToSet: '$session_id' }
        }
    },
    {
        $addFields: {
            session_count: { $size: '$unique_sessions' },
            event_type_count: { $size: '$event_types' }
        }
    },
    {
        $project: {
            unique_sessions: 0
        }
    }
]);

// 创建实时指标视图
db.createView('realtime_metrics', 'analytics', [
    {
        $match: {
            timestamp: { $gte: new Date(Date.now() - 24 * 60 * 60 * 1000) } // 最近24小时
        }
    },
    {
        $group: {
            _id: {
                hour: { $hour: '$timestamp' },
                event_type: '$event_type'
            },
            count: { $sum: 1 },
            unique_users: { $addToSet: '$user_id' }
        }
    },
    {
        $addFields: {
            unique_user_count: { $size: '$unique_users' }
        }
    },
    {
        $project: {
            unique_users: 0
        }
    },
    {
        $sort: { '_id.hour': 1, '_id.event_type': 1 }
    }
]);

print('聚合视图创建完成');

// ================================
// 7. 插入示例数据
// ================================
print('\n插入示例数据...');

// 插入产品示例数据
db.products.insertMany([
    {
        name: 'iPhone 15 Pro',
        sku: 'IPHONE15PRO-128GB-BLUE',
        price: 999.99,
        category_id: 'smartphones',
        description: 'Latest iPhone with advanced features',
        attributes: {
            color: 'Blue',
            storage: '128GB',
            screen_size: '6.1 inch'
        },
        variants: [
            { color: 'Blue', storage: '128GB', price: 999.99 },
            { color: 'Blue', storage: '256GB', price: 1099.99 }
        ],
        tags: ['smartphone', 'apple', 'premium'],
        status: 'active',
        created_at: new Date()
    },
    {
        name: 'MacBook Pro 16"',
        sku: 'MBP16-M3-512GB',
        price: 2499.99,
        category_id: 'laptops',
        description: 'Professional laptop for developers',
        attributes: {
            processor: 'M3 Pro',
            memory: '18GB',
            storage: '512GB SSD'
        },
        tags: ['laptop', 'apple', 'professional'],
        status: 'active',
        created_at: new Date()
    }
]);

// 插入位置示例数据
db.locations.insertMany([
    {
        name: 'Apple Store Fifth Avenue',
        type: 'store',
        location: {
            type: 'Point',
            coordinates: [-73.9731, 40.7589] // [经度, 纬度]
        },
        address: '767 5th Ave, New York, NY 10153',
        properties: {
            phone: '+1-212-336-1440',
            hours: '24/7'
        },
        created_at: new Date()
    },
    {
        name: 'Central Warehouse',
        type: 'warehouse',
        location: {
            type: 'Point',
            coordinates: [-74.0060, 40.7128]
        },
        address: 'New York, NY',
        properties: {
            capacity: 10000,
            type: 'distribution'
        },
        created_at: new Date()
    }
]);

// 插入IoT数据示例
db.iot_data.insertMany([
    {
        device_id: 'sensor_001',
        device_type: 'temperature_sensor',
        timestamp: new Date(),
        data: {
            temperature: 23.5,
            humidity: 65.2,
            battery: 85
        },
        location: {
            type: 'Point',
            coordinates: [-73.9857, 40.7484]
        }
    },
    {
        device_id: 'sensor_002',
        device_type: 'air_quality_sensor',
        timestamp: new Date(),
        data: {
            pm25: 12.3,
            pm10: 18.7,
            co2: 410
        },
        location: {
            type: 'Point',
            coordinates: [-73.9857, 40.7484]
        }
    }
]);

// 插入分析数据示例
db.analytics.insertMany([
    {
        user_id: 'user_001',
        event_type: 'page_view',
        timestamp: new Date(),
        session_id: 'session_001',
        properties: {
            page: '/products',
            referrer: 'google.com'
        },
        page_url: '/products',
        user_agent: 'Mozilla/5.0...',
        ip_address: '192.168.1.1'
    },
    {
        user_id: 'user_001',
        event_type: 'product_click',
        timestamp: new Date(),
        session_id: 'session_001',
        properties: {
            product_id: 'IPHONE15PRO-128GB-BLUE',
            category: 'smartphones'
        }
    }
]);

// 插入全球数据示例
db.global_data.insertMany([
    {
        global_id: 'global_config_001',
        region: 'us-east',
        data_type: 'configuration',
        data: {
            feature_flags: {
                new_checkout: true,
                beta_features: false
            }
        },
        sync_status: 'synced',
        last_sync_time: new Date(),
        version: 1,
        created_at: new Date()
    },
    {
        global_id: 'global_config_001',
        region: 'eu-central',
        data_type: 'configuration',
        data: {
            feature_flags: {
                new_checkout: true,
                beta_features: false
            }
        },
        sync_status: 'synced',
        last_sync_time: new Date(),
        version: 1,
        created_at: new Date()
    }
]);

print('示例数据插入完成');

// ================================
// 8. 分片配置 (如果在分片环境中)
// ================================
print('\n分片配置命令 (需要在mongos中执行):');
print('sh.enableSharding("thinkphp_mongodb")');
print('sh.shardCollection("thinkphp_mongodb.iot_data", {device_id: 1, timestamp: 1})');
print('sh.shardCollection("thinkphp_mongodb.products", {category_id: 1, created_at: 1})');
print('sh.shardCollection("thinkphp_mongodb.analytics", {user_id: "hashed"})');

// ================================
// 9. 创建用户和权限
// ================================
print('\n创建数据库用户...');

// 创建应用程序用户
db.createUser({
    user: 'thinkphp_app',
    pwd: 'your_secure_password_here',
    roles: [
        {
            role: 'readWrite',
            db: 'thinkphp_mongodb'
        }
    ]
});

// 创建只读用户
db.createUser({
    user: 'thinkphp_readonly',
    pwd: 'your_readonly_password_here',
    roles: [
        {
            role: 'read',
            db: 'thinkphp_mongodb'
        }
    ]
});

print('数据库用户创建完成');

print('\n=================================');
print('MongoDB初始化完成!');
print('=================================');
print('\n集合统计:');
print('- products: ' + db.products.countDocuments());
print('- iot_data: ' + db.iot_data.countDocuments());
print('- locations: ' + db.locations.countDocuments());
print('- analytics: ' + db.analytics.countDocuments());
print('- global_data: ' + db.global_data.countDocuments());

print('\n索引统计:');
db.getCollectionNames().forEach(function(collection) {
    var indexes = db.getCollection(collection).getIndexes();
    print('- ' + collection + ': ' + indexes.length + ' 个索引');
});

print('\n初始化脚本执行完成！');