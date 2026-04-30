DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'sales' AND column_name = 'store_id'
    ) THEN
        ALTER TABLE sales ADD COLUMN store_id INTEGER REFERENCES stores(id);
        CREATE INDEX IF NOT EXISTS idx_sales_store ON sales(store_id);
    END IF;
END $$;
