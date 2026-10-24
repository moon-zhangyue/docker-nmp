package main

import (
	"bytes"
	"database/sql"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/stretchr/testify/assert"
)

// setupTestDB 设置测试数据库
func setupTestDB(t *testing.T) *Database {
	// 使用 MySQL 9 进行测试
	dsn := "root:123456@tcp(mysql-9:3306)/test_db?charset=utf8mb4&parseTime=True&loc=Local"
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		t.Fatalf("Failed to open database: %v", err)
	}

	// 测试连接
	if err := db.Ping(); err != nil {
		t.Skipf("MySQL 9 is not available, skipping tests: %v", err)
	}

	return &Database{DB: db}
}

// setupTestRouter 设置测试路由
func setupTestRouter(db *Database) *gin.Engine {
	gin.SetMode(gin.TestMode)
	r := gin.New()
	SetupRoutes(r, db)
	return r
}

// TestHealthCheck 测试健康检查
func TestHealthCheck(t *testing.T) {
	r := setupTestRouter(nil)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/v1/health", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]string
	json.Unmarshal(w.Body.Bytes(), &response)
	assert.Equal(t, "ok", response["status"])
}

// TestCreateUser 测试创建用户
func TestCreateUser(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	// 初始化表
	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 准备测试数据
	userData := map[string]interface{}{
		"username": "testuser",
		"email":    "test@example.com",
		"age":      25,
	}
	body, _ := json.Marshal(userData)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/v1/users", bytes.NewBuffer(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusCreated, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)
	assert.Equal(t, "User created successfully", response["message"])
}

// TestGetUser 测试获取用户
func TestGetUser(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 先创建一个用户
	_, err := db.CreateUser("getuser", "get@example.com", 30)
	if err != nil {
		t.Fatalf("Failed to create user: %v", err)
	}

	// 测试获取用户
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/v1/users/1", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var user User
	json.Unmarshal(w.Body.Bytes(), &user)
	assert.Equal(t, "getuser", user.Username)
	assert.Equal(t, "get@example.com", user.Email)
}

// TestGetAllUsers 测试获取所有用户
func TestGetAllUsers(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 创建多个用户
	db.CreateUser("user1", "user1@example.com", 20)
	db.CreateUser("user2", "user2@example.com", 25)
	db.CreateUser("user3", "user3@example.com", 30)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/v1/users", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	var response map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &response)
	assert.GreaterOrEqual(t, int(response["count"].(float64)), 3)
}

// TestUpdateUser 测试更新用户
func TestUpdateUser(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 先创建一个用户
	id, err := db.CreateUser("oldname", "old@example.com", 25)
	if err != nil {
		t.Fatalf("Failed to create user: %v", err)
	}

	// 更新用户
	updateData := map[string]interface{}{
		"username": "newname",
		"email":    "new@example.com",
		"age":      30,
	}
	body, _ := json.Marshal(updateData)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("PUT", "/api/v1/users/"+string(rune(id)), bytes.NewBuffer(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	// 验证更新
	user, err := db.GetUserByID(int(id))
	if err != nil {
		t.Fatalf("Failed to get user: %v", err)
	}
	assert.Equal(t, "newname", user.Username)
	assert.Equal(t, "new@example.com", user.Email)
	assert.Equal(t, 30, user.Age)
}

// TestDeleteUser 测试删除用户
func TestDeleteUser(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 先创建一个用户
	id, err := db.CreateUser("deleteuser", "delete@example.com", 25)
	if err != nil {
		t.Fatalf("Failed to create user: %v", err)
	}

	// 删除用户
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("DELETE", "/api/v1/users/"+string(rune(id)), nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)

	// 验证删除
	_, err = db.GetUserByID(int(id))
	assert.Error(t, err)
	assert.Equal(t, "user not found", err.Error())
}

// TestCreateUserValidation 测试创建用户的输入验证
func TestCreateUserValidation(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	testCases := []struct {
		name         string
		userData     map[string]interface{}
		expectedCode int
	}{
		{
			name: "Valid user",
			userData: map[string]interface{}{
				"username": "validuser",
				"email":    "valid@example.com",
				"age":      25,
			},
			expectedCode: http.StatusCreated,
		},
		{
			name: "Username too short",
			userData: map[string]interface{}{
				"username": "ab",
				"email":    "short@example.com",
				"age":      25,
			},
			expectedCode: http.StatusBadRequest,
		},
		{
			name: "Invalid email",
			userData: map[string]interface{}{
				"username": "invalidemail",
				"email":    "not-an-email",
				"age":      25,
			},
			expectedCode: http.StatusBadRequest,
		},
		{
			name: "Age too high",
			userData: map[string]interface{}{
				"username": "olduser",
				"email":    "old@example.com",
				"age":      200,
			},
			expectedCode: http.StatusBadRequest,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			body, _ := json.Marshal(tc.userData)
			w := httptest.NewRecorder()
			req, _ := http.NewRequest("POST", "/api/v1/users", bytes.NewBuffer(body))
			req.Header.Set("Content-Type", "application/json")
			r.ServeHTTP(w, req)

			assert.Equal(t, tc.expectedCode, w.Code)
		})
	}
}

// TestUserNotFound 测试用户不存在的情况
func TestUserNotFound(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	if err := db.InitDatabase(); err != nil {
		t.Fatalf("Failed to init database: %v", err)
	}

	r := setupTestRouter(db)

	// 尝试获取不存在的用户
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/v1/users/99999", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusNotFound, w.Code)

	var response map[string]string
	json.Unmarshal(w.Body.Bytes(), &response)
	assert.Equal(t, "User not found", response["error"])
}
