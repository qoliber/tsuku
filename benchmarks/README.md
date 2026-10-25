# Tsuku Benchmarks

Performance benchmarks for Tsuku templating library.

## Running Benchmarks

Run all benchmarks:
```bash
php -n benchmarks/run-all.php
```

`-n` starts PHP without php.ini, so without Xdebug and OPcache. `run-all.php` starts every benchmark with the same
PHP binary and the same `-n` setting, and prints the PHP version, CPU and whether Xdebug is loaded. With Xdebug
loaded (`xdebug.mode=develop`) the benchmarks below ran about 5x slower.

Run individual benchmarks:
```bash
php -n benchmarks/simple.php      # Simple template with loops
php -n benchmarks/complex.php     # Complex nested structures
php -n benchmarks/variables.php   # 1000 variables
php -n benchmarks/csv-export.php  # Real-world CSV export
```

## Benchmark Results

Tsuku 1.4.0, PHP 8.3 with `php -n`, Intel Core Ultra 7 265K, Linux; median of 9 runs of
`php -n benchmarks/run-all.php`. Your hardware will differ.

| Benchmark | Scenario | Per render | Throughput |
|-----------|----------|------------|------------|
| **Simple** | CSV export with 100 products | 0.16 ms | ~6,400 renders/sec |
| **Complex** | 10 categories × 20 products: nested loops, conditions, `@match`, functions | 0.56 ms | ~1,800 renders/sec |
| **Variables** | 1,000 simple variables | 1.3 ms | ~760 renders/sec |
| **CSV Export** | 1,000 products with `@csv`, `@number`, `@match` | 2.9 ms | ~350 exports/sec (~350,000 products/sec) |

PHP 8.1 and PHP 8.5 on the same machine were within 10% of these numbers.

## Performance Characteristics

- **Lexer/Parser**: one pass over the template; conditions are parsed once per template into an expression tree
- **No compilation cache**: every `process()` call parses the template again (intentional for simplicity), once per
  call, not once per row
- **Memory**: `process()` holds the whole output; [`processToStream()`](https://tsukuru.dev/guide/streaming) keeps it
  bounded by the largest single row

## When Performance Matters

Tsuku is optimized for:
- ✅ Batch exports (CSV, XML, JSON)
- ✅ Config file generation
- ✅ API response formatting
- ✅ Data transformations

**Not optimized for:**
- ❌ Ultra-high-frequency rendering (100,000+ renders/sec)
- ❌ Real-time templates with caching disabled

## Optimization Tips

For maximum performance:

1. **Reuse Tsuku instance** (registry initialization happens once)
   ```php
   $tsuku = new Tsuku(); // Create once
   foreach ($batches as $batch) {
       $tsuku->process($template, $batch); // Reuse
   }
   ```

2. **Render many rows in one call**: one `process()` call over 10,000 rows parses the template once;
   10,000 calls parse it 10,000 times

3. **Minimize template complexity**
   - Fewer nested loops = faster rendering
   - Simpler conditions = less evaluation

4. **Profile before optimizing**
   - Tsuku is likely not your bottleneck
   - Database queries, I/O typically slower

## Contributing

To add a new benchmark:

1. Create `benchmarks/your-benchmark.php`
2. Follow existing format (iterations, timing, output)
3. Add to `run-all.php`
4. Submit PR with benchmark results

## Notes

- Benchmarks use `microtime(true)` for precision
- Results vary by hardware, PHP version, and load
- Warm-up iterations not included (negligible impact)
- More on performance and when to choose a compiled engine instead:
  [tsukuru.dev/guide/performance](https://tsukuru.dev/guide/performance)
