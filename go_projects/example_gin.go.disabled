//go:build ignore

package main

import (
	"net/http"

	"github.com/gin-gonic/gin"
)

func main() {
	r := gin.Default()

	// GET 请求
	r.GET("/ping", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"message": "pong",
		})
	})

	// 带参数的 GET 请求
	r.GET("/user/:name", func(c *gin.Context) {
		name := c.Param("name")
		c.JSON(http.StatusOK, gin.H{
			"message": "Hello " + name,
		})
	})

	// POST 请求
	r.POST("/login", func(c *gin.Context) {
		username := c.PostForm("username")
		password := c.PostForm("password")

		c.JSON(http.StatusOK, gin.H{
			"username": username,
			"password": password,
			"status":   "success",
		})
	})

	// JSON 响应
	r.GET("/api/info", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"title":   "Golang Docker Example",
			"version": "1.0.0",
			"author":  "Your Name",
		})
	})

	// 启动服务器（默认端口 8080）
	r.Run()
}
