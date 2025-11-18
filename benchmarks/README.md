# Tsuku Benchmarks

Performance benchmarks for Tsuku templating library.

## Running Benchmarks

Run all benchmarks:
```bash
php benchmarks/run-all.php
```

Run individual benchmarks:
```bash
php benchmarks/simple.php      # Simple template with loops
php benchmarks/complex.php     # Complex nested structures
php benchmarks/variables.php   # 1000 variables
php benchmarks/csv-export.php  # Real-world CSV export
```

## Benchmark Results

Typical results on modern hardware (PHP 8.1+):

| Benchmark | Scenario | Performance |
|-----------|----------|-------------|
| **Simple** | CSV export with 100 products | ~0.2ms per render |
| **Complex** | Nested loops + conditions + match | ~3-5ms per render |
| **Variables** | 1000 simple variables | ~2ms per render |
| **CSV Export** | 1,000 products with escaping | ~10-15ms per export |

**Throughput:**
- Simple templates: **5,000+ renders/sec**
- Complex templates: **200-300 renders/sec**
- CSV exports: **60-100 exports/sec** (60,000+ products/sec)

## Performance Characteristics

- **Lexer/Parser**: Single-pass, regex-based (efficient)
- **Memory**: Low (~60KB per render)
- **No compilation cache**: Each render parses fresh (intentional for flexibility)
- **Bottlenecks**: None for typical workloads

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

2. **Use appropriate strictness mode**
   ```php
   // SILENT is fastest (no warning collection)
   $tsuku = new Tsuku(StrictnessMode::SILENT);
   ```

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
- Memory usage measured with `memory_get_usage()`
