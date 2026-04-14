# syntax=docker/dockerfile:1

FROM golang:1.26-alpine AS builder

WORKDIR /app

RUN apk add --no-cache git

COPY go.mod go.sum ./
RUN /usr/local/go/bin/go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux /usr/local/go/bin/go build -o app .

FROM alpine:3.22

RUN apk add --no-cache ca-certificates

WORKDIR /root/

COPY --from=builder /app/app ./app

EXPOSE 8080

CMD ["./app"]
