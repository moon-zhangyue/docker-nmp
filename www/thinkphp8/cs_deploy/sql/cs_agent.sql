-- ============================================================
-- 智能客服 Agent - 数据库结构 + 种子数据
-- 数据库: cs_agent   表前缀: cs_
-- 目标: mysql-9 (MySQL 9.3)  docker 容器
-- ============================================================
CREATE DATABASE IF NOT EXISTS cs_agent DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cs_agent;

-- ----------------------------
-- 1. 会话表
-- ----------------------------
DROP TABLE IF EXISTS cs_conversation;
CREATE TABLE cs_conversation (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    VARCHAR(64)  NOT NULL COMMENT '会话唯一标识',
  user_id       VARCHAR(64)  DEFAULT '' COMMENT '用户标识',
  user_name     VARCHAR(64)  DEFAULT '访客' COMMENT '用户昵称',
  status        TINYINT      NOT NULL DEFAULT 1 COMMENT '1进行中 2已转人工 3已结束',
  last_intent   VARCHAR(32)  DEFAULT 'other' COMMENT '最近意图',
  transferred   TINYINT      NOT NULL DEFAULT 0 COMMENT '是否转人工',
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at     DATETIME     DEFAULT NULL,
  UNIQUE KEY uk_session (session_id),
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服会话表';

-- ----------------------------
-- 2. 消息表
-- ----------------------------
DROP TABLE IF EXISTS cs_message;
CREATE TABLE cs_message (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    VARCHAR(64)  NOT NULL COMMENT '会话唯一标识',
  role          VARCHAR(16)  NOT NULL COMMENT 'user/assistant/system/agent',
  content       TEXT         NOT NULL COMMENT '消息内容',
  intent        VARCHAR(32)  DEFAULT '' COMMENT '识别意图',
  source        VARCHAR(32)  DEFAULT '' COMMENT '回复来源: faq/llm/agent/rule',
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  KEY idx_session (session_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='对话消息表';

-- ----------------------------
-- 3. FAQ 知识库表
-- ----------------------------
DROP TABLE IF EXISTS cs_faq;
CREATE TABLE cs_faq (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question      VARCHAR(500) NOT NULL COMMENT '标准问题',
  answer        TEXT         NOT NULL COMMENT '标准答案',
  keywords      VARCHAR(500) DEFAULT '' COMMENT '关键词,逗号分隔',
  category      VARCHAR(64)  DEFAULT '通用' COMMENT '分类',
  intent        VARCHAR(32)  DEFAULT 'other' COMMENT '关联意图',
  hit_count     INT          NOT NULL DEFAULT 0 COMMENT '命中次数',
  status        TINYINT      NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_intent (intent),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='FAQ知识库表';

-- ----------------------------
-- 4. 人工工单表
-- ----------------------------
DROP TABLE IF EXISTS cs_ticket;
CREATE TABLE cs_ticket (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no     VARCHAR(32)  NOT NULL COMMENT '工单号',
  session_id    VARCHAR(64)  NOT NULL COMMENT '会话唯一标识',
  user_question TEXT         NOT NULL COMMENT '触发转人工的问题',
  reason        VARCHAR(128) DEFAULT '' COMMENT '转人工原因',
  intent        VARCHAR(32)  DEFAULT 'other' COMMENT '意图',
  status        TINYINT      NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已完成',
  assignee      VARCHAR(64)  DEFAULT '' COMMENT '客服处理人',
  reply         TEXT         DEFAULT NULL COMMENT '人工回复',
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ticket (ticket_no),
  KEY idx_session (session_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人工工单表';

-- ----------------------------
-- 5. 满意度评价表
-- ----------------------------
DROP TABLE IF EXISTS cs_satisfaction;
CREATE TABLE cs_satisfaction (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    VARCHAR(64)  NOT NULL COMMENT '会话唯一标识',
  rating        TINYINT      NOT NULL COMMENT '评分1-5',
  comment       VARCHAR(500) DEFAULT '' COMMENT '评价留言',
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  KEY idx_session (session_id),
  KEY idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='满意度评价表';

-- ============================================================
-- FAQ 种子数据
-- ============================================================
INSERT INTO cs_faq (question, answer, keywords, category, intent) VALUES
('如何申请退款？',
 '您可以在「我的订单」中找到对应订单，点击「申请退款」，填写退款原因并提交。审核通过后，款项将在1-7个工作日内原路退回。如需帮助，可提供订单号。',
 '退款,退货,申请退款,怎么退款,退钱', '售后服务', 'refund'),
('退款多久能到账？',
 '退款审核通过后，通常1-7个工作日内原路退回至您的支付账户。微信/支付宝一般1-3天，银行卡可能需要3-7天，具体以银行处理时间为准。',
 '退款到账,多久到账,退款时间,几天到账', '售后服务', 'refund'),
('订单取消后什么时候退款？',
 '订单取消后，系统会自动发起退款，款项将在1-7个工作日内原路退回。若超时未收到，请提供订单号以便我们协助查询。',
 '取消订单,取消退款,订单取消', '售后服务', 'refund'),
('如何查询我的订单状态？',
 '请登录账号后进入「我的订单」查看。您可以提供订单号，我可以帮您查询具体的物流与订单状态。',
 '查订单,订单状态,查询订单,物流,我的订单', '订单服务', 'order_query'),
('订单什么时候发货？',
 '现货商品通常在付款后24-48小时内发货，预售商品以商品页标注的发货时间为准。发货后可在订单详情查看物流单号。',
 '发货,什么时候发货,发货时间,物流单号', '订单服务', 'order_query'),
('如何修改收货地址？',
 '订单未发货前，可在订单详情点击「修改地址」进行调整。若已发货，请联系客服或物流公司处理。',
 '修改地址,收货地址,改地址', '订单服务', 'order_query'),
('忘记密码怎么办？',
 '在登录页点击「忘记密码」，通过注册手机号或邮箱验证后即可重置密码。如无法接收验证码，请联系人工客服。',
 '忘记密码,重置密码,找回密码', '账号问题', 'tech_support'),
('无法登录怎么办？',
 '请检查账号密码是否正确，或尝试清除缓存后重试。若多次失败账号可能被临时锁定，请稍后再试或联系人工客服。',
 '无法登录,登录失败,登不上', '账号问题', 'tech_support'),
('App闪退/打不开怎么办？',
 '建议：1)升级到最新版本；2)清理缓存；3)重启设备；4)检查网络连接。若问题持续，请提供设备型号和系统版本，我们将协助排查。',
 '闪退,打不开,崩溃,卡顿,app问题', '技术支持', 'tech_support'),
('如何联系人工客服？',
 '您可以直接说「转人工」，我将为您创建工单并转接人工客服。人工服务时间为每天9:00-21:00。',
 '人工客服,转人工,人工,客服电话,联系客服', '通用', 'other'),
('支持哪些支付方式？',
 '我们支持微信支付、支付宝、银联银行卡以及信用卡支付，具体以结算页面展示为准。',
 '支付方式,微信支付,支付宝,银联,付款方式', '通用', 'other'),
('如何开具发票？',
 '下单时可在订单备注中填写开票信息，或订单完成后在订单详情申请电子发票，一般1-3个工作日开具。',
 '发票,开票,电子发票', '售后服务', 'other');

SELECT 'Database initialized.' AS result, (SELECT COUNT(*) FROM cs_faq) AS faq_count;
