package main

import (
	"log"
)

func main() {
	// 初始化数据库连接
	db, err := NewDatabase()
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}
	defer db.Close()

	// 初始化数据库和表
	if err := db.InitDatabase(); err != nil {
		log.Fatalf("Failed to initialize database: %v", err)
	}

	// 创建 Gin 引擎
	r := NewGinEngine(db)

	// 启动服务器（默认端口 8080）
	log.Println("Starting server on :8080")
	if err := r.Run(); err != nil {
		log.Fatalf("Failed to start server: %v", err)
	}
}
