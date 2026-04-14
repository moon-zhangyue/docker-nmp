package main

import (
	"context"
	"fmt"
	"os"
	"testing"
	"time"
)

func TestMySQLStoreIntegration(t *testing.T) {
	if os.Getenv("MYSQL9_INTEGRATION") != "1" {
		t.Skip("set MYSQL9_INTEGRATION=1 to run MySQL integration tests")
	}

	cfg := DefaultDatabaseConfig()
	if os.Getenv("MYSQL9_HOST") == "" && !runningInDocker() {
		cfg.Host = "localhost"
	}
	if os.Getenv("MYSQL9_PORT") == "" && !runningInDocker() {
		cfg.Port = "3308"
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := ensureDatabaseExists(ctx, cfg); err != nil {
		t.Fatalf("ensure database exists: %v", err)
	}

	db, err := OpenDB(ctx, cfg)
	if err != nil {
		t.Fatalf("open db: %v", err)
	}
	defer db.Close()

	store := NewMySQLUserStore(db)
	if err := store.EnsureSchema(ctx); err != nil {
		t.Fatalf("ensure schema: %v", err)
	}

	if _, err := db.ExecContext(ctx, `DELETE FROM users`); err != nil {
		t.Fatalf("clear users table: %v", err)
	}

	id, err := store.CreateUser(ctx, CreateUserInput{
		Username: "integration-user",
		Email:    "integration@example.com",
		Age:      26,
	})
	if err != nil {
		t.Fatalf("create user: %v", err)
	}

	user, err := store.GetUser(ctx, id)
	if err != nil {
		t.Fatalf("get user: %v", err)
	}
	if user.Username != "integration-user" {
		t.Fatalf("unexpected username: %s", user.Username)
	}

	if err := store.UpdateUser(ctx, id, UpdateUserInput{
		Username: "integration-user-updated",
		Email:    "integration.updated@example.com",
		Age:      27,
	}); err != nil {
		t.Fatalf("update user: %v", err)
	}

	users, err := store.ListUsers(ctx)
	if err != nil {
		t.Fatalf("list users: %v", err)
	}
	if len(users) != 1 {
		t.Fatalf("expected 1 user, got %d", len(users))
	}

	if err := store.DeleteUser(ctx, id); err != nil {
		t.Fatalf("delete user: %v", err)
	}
}

func runningInDocker() bool {
	_, err := os.Stat("/.dockerenv")
	return err == nil
}

func ensureDatabaseExists(ctx context.Context, cfg DatabaseConfig) error {
	adminConfig := cfg
	adminConfig.DBName = ""

	adminDB, err := OpenDB(ctx, adminConfig)
	if err != nil {
		return err
	}
	defer adminDB.Close()

	query := fmt.Sprintf(
		"CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
		cfg.DBName,
	)
	if _, err := adminDB.ExecContext(ctx, query); err != nil {
		return err
	}

	return nil
}
