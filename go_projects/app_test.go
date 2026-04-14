package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strconv"
	"sync"
	"testing"
	"time"

	"github.com/gin-gonic/gin"
)

type fakeUserStore struct {
	mu      sync.Mutex
	users   map[int64]User
	nextID  int64
	pingErr error
}

func newFakeUserStore() *fakeUserStore {
	return &fakeUserStore{
		users:  make(map[int64]User),
		nextID: 1,
	}
}

func (s *fakeUserStore) Ping(context.Context) error {
	return s.pingErr
}

func (s *fakeUserStore) EnsureSchema(context.Context) error {
	return nil
}

func (s *fakeUserStore) ListUsers(context.Context) ([]User, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	users := make([]User, 0, len(s.users))
	for _, user := range s.users {
		users = append(users, user)
	}

	return users, nil
}

func (s *fakeUserStore) GetUser(_ context.Context, id int64) (*User, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	user, ok := s.users[id]
	if !ok {
		return nil, ErrUserNotFound
	}

	copy := user
	return &copy, nil
}

func (s *fakeUserStore) CreateUser(_ context.Context, input CreateUserInput) (int64, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	now := time.Now().UTC()
	id := s.nextID
	s.nextID++

	s.users[id] = User{
		ID:        id,
		Username:  input.Username,
		Email:     input.Email,
		Age:       input.Age,
		CreatedAt: now,
		UpdatedAt: now,
	}

	return id, nil
}

func (s *fakeUserStore) UpdateUser(_ context.Context, id int64, input UpdateUserInput) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	user, ok := s.users[id]
	if !ok {
		return ErrUserNotFound
	}

	user.Username = input.Username
	user.Email = input.Email
	user.Age = input.Age
	user.UpdatedAt = time.Now().UTC()
	s.users[id] = user

	return nil
}

func (s *fakeUserStore) DeleteUser(_ context.Context, id int64) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if _, ok := s.users[id]; !ok {
		return ErrUserNotFound
	}

	delete(s.users, id)
	return nil
}

func newTestRouter(store UserStore) *gin.Engine {
	gin.SetMode(gin.TestMode)
	return NewGinApp(store)
}

func decodeBody(t *testing.T, body *bytes.Buffer) map[string]any {
	t.Helper()

	var payload map[string]any
	if err := json.Unmarshal(body.Bytes(), &payload); err != nil {
		t.Fatalf("decode response body: %v", err)
	}

	return payload
}

func TestHealthCheck(t *testing.T) {
	router := newTestRouter(newFakeUserStore())

	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	body := decodeBody(t, rec.Body)
	if body["status"] != "ok" {
		t.Fatalf("expected ok status, got %#v", body["status"])
	}
}

func TestTestDBConnection(t *testing.T) {
	store := newFakeUserStore()
	router := newTestRouter(store)

	req := httptest.NewRequest(http.MethodGet, "/api/db/test", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	body := decodeBody(t, rec.Body)
	if body["status"] != "success" {
		t.Fatalf("expected success status, got %#v", body["status"])
	}
}

func TestTestDBConnectionFailure(t *testing.T) {
	store := newFakeUserStore()
	store.pingErr = errors.New("mysql ping failed")

	router := newTestRouter(store)

	req := httptest.NewRequest(http.MethodGet, "/api/db/test", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusInternalServerError {
		t.Fatalf("expected 500, got %d", rec.Code)
	}
}

func TestCreateUser(t *testing.T) {
	router := newTestRouter(newFakeUserStore())

	payload := []byte(`{"username":"alice","email":"alice@example.com","age":28}`)
	req := httptest.NewRequest(http.MethodPost, "/api/users", bytes.NewReader(payload))
	req.Header.Set("Content-Type", "application/json")

	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusCreated {
		t.Fatalf("expected 201, got %d", rec.Code)
	}

	body := decodeBody(t, rec.Body)
	if body["status"] != "success" {
		t.Fatalf("expected success status, got %#v", body["status"])
	}
}

func TestCreateUserWithInvalidEmail(t *testing.T) {
	router := newTestRouter(newFakeUserStore())

	payload := []byte(`{"username":"alice","email":"bad-email","age":28}`)
	req := httptest.NewRequest(http.MethodPost, "/api/users", bytes.NewReader(payload))
	req.Header.Set("Content-Type", "application/json")

	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Fatalf("expected 400, got %d", rec.Code)
	}
}

func TestGetUsers(t *testing.T) {
	store := newFakeUserStore()
	_, _ = store.CreateUser(context.Background(), CreateUserInput{
		Username: "alice",
		Email:    "alice@example.com",
		Age:      28,
	})
	_, _ = store.CreateUser(context.Background(), CreateUserInput{
		Username: "bob",
		Email:    "bob@example.com",
		Age:      31,
	})

	router := newTestRouter(store)

	req := httptest.NewRequest(http.MethodGet, "/api/users", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	body := decodeBody(t, rec.Body)
	if body["count"] != float64(2) {
		t.Fatalf("expected count 2, got %#v", body["count"])
	}
}

func TestGetUserByID(t *testing.T) {
	store := newFakeUserStore()
	id, _ := store.CreateUser(context.Background(), CreateUserInput{
		Username: "charlie",
		Email:    "charlie@example.com",
		Age:      25,
	})

	router := newTestRouter(store)

	req := httptest.NewRequest(http.MethodGet, "/api/users/"+strconv.FormatInt(id, 10), nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	body := decodeBody(t, rec.Body)
	if body["status"] != "success" {
		t.Fatalf("expected success status, got %#v", body["status"])
	}
}

func TestUpdateUser(t *testing.T) {
	store := newFakeUserStore()
	id, _ := store.CreateUser(context.Background(), CreateUserInput{
		Username: "old-name",
		Email:    "old@example.com",
		Age:      20,
	})

	router := newTestRouter(store)

	payload := []byte(`{"username":"new-name","email":"new@example.com","age":30}`)
	req := httptest.NewRequest(http.MethodPut, "/api/users/"+strconv.FormatInt(id, 10), bytes.NewReader(payload))
	req.Header.Set("Content-Type", "application/json")

	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	user, err := store.GetUser(context.Background(), id)
	if err != nil {
		t.Fatalf("expected user to exist: %v", err)
	}
	if user.Username != "new-name" {
		t.Fatalf("expected updated username, got %q", user.Username)
	}
}

func TestDeleteUser(t *testing.T) {
	store := newFakeUserStore()
	id, _ := store.CreateUser(context.Background(), CreateUserInput{
		Username: "delete-me",
		Email:    "delete@example.com",
		Age:      22,
	})

	router := newTestRouter(store)

	req := httptest.NewRequest(http.MethodDelete, "/api/users/"+strconv.FormatInt(id, 10), nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	if _, err := store.GetUser(context.Background(), id); !errors.Is(err, ErrUserNotFound) {
		t.Fatalf("expected deleted user to be missing, got %v", err)
	}
}

func TestGetMissingUser(t *testing.T) {
	router := newTestRouter(newFakeUserStore())

	req := httptest.NewRequest(http.MethodGet, "/api/users/999", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusNotFound {
		t.Fatalf("expected 404, got %d", rec.Code)
	}
}

func TestInvalidUserID(t *testing.T) {
	router := newTestRouter(newFakeUserStore())

	req := httptest.NewRequest(http.MethodGet, "/api/users/not-a-number", nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Fatalf("expected 400, got %d", rec.Code)
	}
}
