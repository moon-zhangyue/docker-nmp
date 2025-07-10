<?php
declare(strict_types=1);

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePostgresFunctions extends Migrator
{
    /**
     * Change Method.
     *
     * 此方法在执行迁移时被调用
     */
    public function change()
    {
        // 创建table_msg函数用于兼容ThinkPHP的查询
        $this->execute("
            CREATE OR REPLACE FUNCTION table_msg(schema_name text)
            RETURNS TABLE(
                name character varying,
                columns json,
                type character varying,
                comment character varying,
                create_time timestamp without time zone,
                engine character varying,
                pk_column character varying,
                default_value character varying,
                extra character varying,
                fields_comment character varying
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    t.table_name::character varying AS name,
                    (
                        SELECT json_object_agg(c.column_name, json_build_object(
                            'name', c.column_name,
                            'type', c.data_type,
                            'length', c.character_maximum_length,
                            'precision', c.numeric_precision,
                            'scale', c.numeric_scale,
                            'nullable', CASE WHEN c.is_nullable = 'YES' THEN true ELSE false END,
                            'default', c.column_default,
                            'comment', (
                                SELECT pg_catalog.col_description(pg_catalog.pg_class.oid, c.ordinal_position)
                                FROM pg_catalog.pg_class
                                WHERE pg_catalog.pg_class.relname = c.table_name
                            )
                        ))
                        FROM information_schema.columns c
                        WHERE c.table_schema = t.table_schema AND c.table_name = t.table_name
                    ) AS columns,
                    'BASE TABLE'::character varying AS type,
                    (
                        SELECT pg_catalog.obj_description(pg_catalog.pg_class.oid, 'pg_class')
                        FROM pg_catalog.pg_class
                        WHERE pg_catalog.pg_class.relname = t.table_name
                    )::character varying AS comment,
                    now() AS create_time,
                    'PostgreSQL'::character varying AS engine,
                    (
                        SELECT c.column_name
                        FROM information_schema.table_constraints tc
                        JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
                        JOIN information_schema.columns c ON c.table_schema = tc.table_schema 
                            AND c.table_name = tc.table_name 
                            AND c.column_name = ccu.column_name
                        WHERE tc.constraint_type = 'PRIMARY KEY'
                            AND tc.table_schema = t.table_schema
                            AND tc.table_name = t.table_name
                        LIMIT 1
                    )::character varying AS pk_column,
                    ''::character varying AS default_value,
                    ''::character varying AS extra,
                    ''::character varying AS fields_comment
                FROM information_schema.tables t
                WHERE t.table_schema = schema_name
                AND t.table_type = 'BASE TABLE';
            END;
            $$ LANGUAGE plpgsql;
        ");
    }
} 