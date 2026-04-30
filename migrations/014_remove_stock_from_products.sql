DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'products' AND column_name = 'stock'
    ) THEN
        ALTER TABLE products DROP COLUMN stock;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'products' AND column_name = 'min_stock'
    ) THEN
        ALTER TABLE products DROP COLUMN min_stock;
    END IF;
END $$;
