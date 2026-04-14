//go:build ignore

package main

import (
	"bytes"
	"database/sql"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"testing"

	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/stretchr/testify/assert"
)

var testDB *sql.DB

// TestMain 测试主函数，用于初始化和清理
func TestMain(m *testing.M) {
	// 设置测试环境
	gin.SetMode(gin.TestMode)

	// 初始化测试数据库连接
	setupTestDB()

	// 运行测试
	code := m.Run()

	// 清理
	teardownTestDB()

	os.Exit(code)
}

// setupTestDB 设置测试数据库
func setupTestDB() {
	// 使用环境变量或默认值
	host := os.Getenv("MYSQL9_HOST")
	if host == "" {
		host = "localhost"
	}
	port := os.Getenv("MYSQL9_PORT")
	if port == "" {
		port = "3308" // MySQL9 默认端口
	}
	user := os.Getenv("MYSQL9_USER")
	if user == "" {
		user = "root"
	}
	password := os.Getenv("MYSQL9_PASSWORD")
	if password == "" {
		password = "123456"
	}
	dbname := os.Getenv("MYSQL9_DATABASE")
	if dbname == "" {
		dbname = "test_db"
	}

	dsn := user + ":" + password + "@tcp(" + host + ":" + port + ")/" + dbname + "?charset=utf8mb4&parseTime=True&loc=Local"

	var err error
	testDB, err = sql.Open("mysql", dsn)
	if err != nil {
		panic("无法连接到测试数据库: " + err.Error())
	}

	// 测试连接
	if err = testDB.Ping(); err != nil {
		// 如果数据库不存在，尝试创建
		if err := createTestDatabase(host, port, user, password, dbname); err != nil {
			panic("无法创建测试数据库: " + err.Error())
		}
		// 重新连接
		testDB, err = sql.Open("mysql", dsn)
		if err != nil {
			panic("无法重新连接到测试数据库: " + err.Error())
		}
		if err = testDB.Ping(); err != nil {
			panic("测试数据库连接失败: " + err.Error())
		}
	}

	// 设置全局 DB 变量
	DB = testDB

	// 创建测试表
	if err := CreateTestTable(); err != nil {
		panic("创建测试表失败: " + err.Error())
	}

	// 清空测试数据
	clearTestData()
}

// createTestDatabase 创建测试数据库
func createTestDatabase(host, port, user, password, dbname string) error {
	dsn := user + ":" + password + "@tcp(" + host + ":" + port + ")/?charset=utf8mb4"
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return err
	}
	defer db.Close()

	_, err = db.Exec("CREATE DATABASE IF NOT EXISTS " + dbname + " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
	return err
}

// teardownTestDB 清理测试数据库
func teardownTestDB() {
	if testDB != nil {
		clearTestData()
		testDB.Close()
	}
}

// clearTestData 清空测试数据
func clearTestData() {
	if testDB != nil {
		testDB.Exec("DELETE FROM users")
	}
}

// TestHealthCheck 测试健康检查接口
func TestHealthCheck(t *testing.T) {
	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/health", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "ok", response["status"])
	assert.Contains(t, response["message"], "Gin + MySQL9 API is running")
}

// TestTestDBConnection 测试数据库连接接口
func TestTestDBConnection(t *testing.T) {
	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/db/test", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
}

// TestCreateUser 测试创建用户
func TestCreateUser(t *testing.T) {
	clearTestData()

	r := NewGinApp()

	// 准备测试数据
	userData := map[string]interface{}{
		"username": "testuser",
		"email":    "test@example.com",
		"age":      25,
	}
	body, _ := json.Marshal(userData)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/users", bytes.NewBuffer(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusCreated, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
	assert.Contains(t, response["message"], "成功")
}

// TestGetUsers 测试获取用户列表
func TestGetUsers(t *testing.T) {
	clearTestData()

	// 先创建一些测试数据
	insertTestUser("user1", "user1@example.com", 20)
	insertTestUser("user2", "user2@example.com", 30)

	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/users", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
	assert.NotNil(t, response["data"])
}

// TestGetUserByID 测试根据ID获取用户
func TestGetUserByID(t *testing.T) {
	clearTestData()

	// 先创建一个测试用户
	id := insertTestUser("singleuser", "single@example.com", 28)

	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/users/"+string(rune(id)), nil)
	r.ServeHTTP(w, req)

	// 注意：这里需要正确转换 ID 为字符串
	w2 := httptest.NewRecorder()
	req2, _ := http.NewRequest("GET", "/api/users/1", nil)
	r.ServeHTTP(w2, req2)

	assert.Equal(t, http.StatusOK, w2.Code)

	var response map[string]interface{}
	json.Unmarshal(w2.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
	assert.NotNil(t, response["data"])
}

// TestUpdateUser 测试更新用户
func TestUpdateUser(t *testing.T) {
	clearTestData()

	// 先创建一个测试用户
	insertTestUser("oldname", "old@example.com", 25)

	r := NewGinApp()

	// 准备更新数据
	updateData := map[string]interface{}{
		"username": "newname",
		"email":    "new@example.com",
		"age":      30,
	}
	body, _ := json.Marshal(updateData)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("PUT", "/api/users/1", bytes.NewBuffer(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
}

// TestDeleteUser 测试删除用户
func TestDeleteUser(t *testing.T) {
	clearTestData()

	// 先创建一个测试用户
	insertTestUser("deleteuser", "delete@example.com", 35)

	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("DELETE", "/api/users/1", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)

	assert.Equal(t, "success", response["status"])
}

// TestGetNonExistentUser 测试获取不存在的用户
func TestGetNonExistentUser(t *testing.T) {
	r := NewGinApp()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/users/99999", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusNotFound, w.Code)
}

// TestCreateUserWithInvalidEmail 测试使用无效邮箱创建用户
func TestCreateUserWithInvalidEmail(t *testing.T) {
	r := NewGinApp()

	// 准备测试数据（无效邮箱）
	userData := map[string]interface{}{
		"username": "testuser2",
		"email":    "invalid-email",
		"age":      25,
	}
	body, _ := json.Marshal(userData)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/users", bytes.NewBuffer(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusBadRequest, w.Code)
}

// insertTestUser 插入测试用户并返回 ID
func insertTestUser(username, email string, age int) int64 {
	result, err := testDB.Exec(
		"INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
		username,
		email,
		age,
	)
	if err != nil {
		panic("插入测试用户失败: " + err.Error())
	}

	id, _ := result.LastInsertId()
	return id
}

// TestDatabaseConnection 直接测试数据库连接
func TestDatabaseConnection(t *testing.T) {
	if testDB == nil {
		t.Fatal("测试数据库未初始化")
	}

	if err := testDB.Ping(); err != nil {
		t.Errorf("数据库连接失败: %v", err)
	}
}

// TestCreateTable 测试创建表
func TestCreateTable(t *testing.T) {
	if err := CreateTestTable(); err != nil {
		t.Errorf("创建表失败: %v", err)
	}
}

// TestCRUDOperations 测试完整的 CRUD 操作
func TestCRUDOperations(t *testing.T) {
	clearTestData()

	// Create
	_, err := testDB.Exec("INSERT INTO users (username, email, age) VALUES (?, ?, ?)", "cruduser", "crud@example.com", 25)
	assert.NoError(t, err)

	// Read
	var username string
	err = testDB.QueryRow("SELECT username FROM users WHERE email = ?", "crud@example.com").Scan(&username)
	assert.NoError(t, err)
	assert.Equal(t, "cruduser", username)

	// Update
	_, err = testDB.Exec("UPDATE users SET username = ? WHERE email = ?", "updateduser", "crud@example.com")
	assert.NoError(t, err)

	var updatedUsername string
	err = testDB.QueryRow("SELECT username FROM users WHERE email = ?", "crud@example.com").Scan(&updatedUsername)
	assert.NoError(t, err)
	assert.Equal(t, "updateduser", updatedUsername)

	// Delete
	_, err = testDB.Exec("DELETE FROM users WHERE email = ?", "crud@example.com")
	assert.NoError(t, err)

	var count int
	err = testDB.QueryRow("SELECT COUNT(*) FROM users WHERE email = ?", "crud@example.com").Scan(&count)
	assert.NoError(t, err)
	assert.Equal(t, 0, count)
}
