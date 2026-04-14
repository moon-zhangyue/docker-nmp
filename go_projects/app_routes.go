package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/gin-gonic/gin"
)

var (
	ErrDatabaseUnavailable = errors.New("database unavailable")
	ErrUserNotFound        = errors.New("user not found")
)

type User struct {
	ID        int64     `json:"id"`
	Username  string    `json:"username"`
	Email     string    `json:"email"`
	Age       int       `json:"age"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

type CreateUserInput struct {
	Username string `json:"username" binding:"required"`
	Email    string `json:"email" binding:"required,email"`
	Age      int    `json:"age"`
}

type UpdateUserInput struct {
	Username string `json:"username" binding:"required"`
	Email    string `json:"email" binding:"required,email"`
	Age      int    `json:"age"`
}

type UserStore interface {
	Ping(ctx context.Context) error
	EnsureSchema(ctx context.Context) error
	ListUsers(ctx context.Context) ([]User, error)
	GetUser(ctx context.Context, id int64) (*User, error)
	CreateUser(ctx context.Context, input CreateUserInput) (int64, error)
	UpdateUser(ctx context.Context, id int64, input UpdateUserInput) error
	DeleteUser(ctx context.Context, id int64) error
}

type MySQLUserStore struct {
	db *sql.DB
}

func NewMySQLUserStore(db *sql.DB) *MySQLUserStore {
	return &MySQLUserStore{db: db}
}

func (s *MySQLUserStore) Ping(ctx context.Context) error {
	if s == nil || s.db == nil {
		return ErrDatabaseUnavailable
	}

	return s.db.PingContext(ctx)
}

func (s *MySQLUserStore) EnsureSchema(ctx context.Context) error {
	if s == nil || s.db == nil {
		return ErrDatabaseUnavailable
	}

	return CreateUsersTable(ctx, s.db)
}

func (s *MySQLUserStore) ListUsers(ctx context.Context) ([]User, error) {
	if s == nil || s.db == nil {
		return nil, ErrDatabaseUnavailable
	}

	rows, err := s.db.QueryContext(
		ctx,
		`SELECT id, username, email, age, created_at, updated_at FROM users ORDER BY id DESC`,
	)
	if err != nil {
		return nil, fmt.Errorf("list users: %w", err)
	}
	defer rows.Close()

	users := make([]User, 0)
	for rows.Next() {
		var user User
		if err := rows.Scan(
			&user.ID,
			&user.Username,
			&user.Email,
			&user.Age,
			&user.CreatedAt,
			&user.UpdatedAt,
		); err != nil {
			return nil, fmt.Errorf("scan user: %w", err)
		}
		users = append(users, user)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate users: %w", err)
	}

	return users, nil
}

func (s *MySQLUserStore) GetUser(ctx context.Context, id int64) (*User, error) {
	if s == nil || s.db == nil {
		return nil, ErrDatabaseUnavailable
	}

	var user User
	err := s.db.QueryRowContext(
		ctx,
		`SELECT id, username, email, age, created_at, updated_at FROM users WHERE id = ?`,
		id,
	).Scan(
		&user.ID,
		&user.Username,
		&user.Email,
		&user.Age,
		&user.CreatedAt,
		&user.UpdatedAt,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrUserNotFound
		}
		return nil, fmt.Errorf("get user %d: %w", id, err)
	}

	return &user, nil
}

func (s *MySQLUserStore) CreateUser(ctx context.Context, input CreateUserInput) (int64, error) {
	if s == nil || s.db == nil {
		return 0, ErrDatabaseUnavailable
	}

	result, err := s.db.ExecContext(
		ctx,
		`INSERT INTO users (username, email, age) VALUES (?, ?, ?)`,
		input.Username,
		input.Email,
		input.Age,
	)
	if err != nil {
		return 0, fmt.Errorf("create user: %w", err)
	}

	id, err := result.LastInsertId()
	if err != nil {
		return 0, fmt.Errorf("read last insert id: %w", err)
	}

	return id, nil
}

func (s *MySQLUserStore) UpdateUser(ctx context.Context, id int64, input UpdateUserInput) error {
	if s == nil || s.db == nil {
		return ErrDatabaseUnavailable
	}

	exists, err := s.userExists(ctx, id)
	if err != nil {
		return err
	}
	if !exists {
		return ErrUserNotFound
	}

	if _, err := s.db.ExecContext(
		ctx,
		`UPDATE users SET username = ?, email = ?, age = ? WHERE id = ?`,
		input.Username,
		input.Email,
		input.Age,
		id,
	); err != nil {
		return fmt.Errorf("update user %d: %w", id, err)
	}

	return nil
}

func (s *MySQLUserStore) DeleteUser(ctx context.Context, id int64) error {
	if s == nil || s.db == nil {
		return ErrDatabaseUnavailable
	}

	result, err := s.db.ExecContext(ctx, `DELETE FROM users WHERE id = ?`, id)
	if err != nil {
		return fmt.Errorf("delete user %d: %w", id, err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("read rows affected for user %d: %w", id, err)
	}
	if rowsAffected == 0 {
		return ErrUserNotFound
	}

	return nil
}

func (s *MySQLUserStore) userExists(ctx context.Context, id int64) (bool, error) {
	var exists int
	err := s.db.QueryRowContext(ctx, `SELECT 1 FROM users WHERE id = ?`, id).Scan(&exists)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return false, nil
		}
		return false, fmt.Errorf("check user %d existence: %w", id, err)
	}

	return true, nil
}

type API struct {
	store UserStore
}

func SetupRoutes(r *gin.Engine, store UserStore) {
	api := API{store: store}

	r.GET("/health", api.HealthCheck)

	group := r.Group("/api")
	group.GET("/db/test", api.TestDBConnection)
	group.GET("/users", api.GetUsers)
	group.GET("/users/:id", api.GetUserByID)
	group.POST("/users", api.CreateUser)
	group.PUT("/users/:id", api.UpdateUser)
	group.DELETE("/users/:id", api.DeleteUser)
}

func (a API) HealthCheck(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status":  "ok",
		"message": "Gin + MySQL9 API is running",
	})
}

func (a API) TestDBConnection(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	if err := a.store.Ping(ctx); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "database connection is healthy",
	})
}

func (a API) GetUsers(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	users, err := a.store.ListUsers(ctx)
	if err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"data":   users,
		"count":  len(users),
	})
}

func (a API) GetUserByID(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	id, ok := parseIDParam(c)
	if !ok {
		return
	}

	user, err := a.store.GetUser(ctx, id)
	if err != nil {
		if errors.Is(err, ErrUserNotFound) {
			respondError(c, http.StatusNotFound, err.Error())
			return
		}
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"data":   user,
	})
}

func (a API) CreateUser(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	var input CreateUserInput
	if err := c.ShouldBindJSON(&input); err != nil {
		respondError(c, http.StatusBadRequest, err.Error())
		return
	}

	id, err := a.store.CreateUser(ctx, input)
	if err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusCreated, gin.H{
		"status":  "success",
		"message": "user created",
		"data": gin.H{
			"id": id,
		},
	})
}

func (a API) UpdateUser(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	id, ok := parseIDParam(c)
	if !ok {
		return
	}

	var input UpdateUserInput
	if err := c.ShouldBindJSON(&input); err != nil {
		respondError(c, http.StatusBadRequest, err.Error())
		return
	}

	if err := a.store.UpdateUser(ctx, id, input); err != nil {
		if errors.Is(err, ErrUserNotFound) {
			respondError(c, http.StatusNotFound, err.Error())
			return
		}
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "user updated",
	})
}

func (a API) DeleteUser(c *gin.Context) {
	ctx, cancel := requestContext(c)
	defer cancel()

	if err := a.requireStore(); err != nil {
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	id, ok := parseIDParam(c)
	if !ok {
		return
	}

	if err := a.store.DeleteUser(ctx, id); err != nil {
		if errors.Is(err, ErrUserNotFound) {
			respondError(c, http.StatusNotFound, err.Error())
			return
		}
		respondError(c, http.StatusInternalServerError, err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":  "success",
		"message": "user deleted",
	})
}

func (a API) requireStore() error {
	if a.store == nil {
		return ErrDatabaseUnavailable
	}

	return nil
}

func requestContext(c *gin.Context) (context.Context, context.CancelFunc) {
	return context.WithTimeout(c.Request.Context(), 3*time.Second)
}

func parseIDParam(c *gin.Context) (int64, bool) {
	id, err := strconv.ParseInt(c.Param("id"), 10, 64)
	if err != nil {
		respondError(c, http.StatusBadRequest, "invalid user id")
		return 0, false
	}

	return id, true
}

func respondError(c *gin.Context, status int, message string) {
	c.JSON(status, gin.H{
		"status":  "error",
		"message": message,
	})
}
