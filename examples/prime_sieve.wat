(module
  (memory (export "memory") 4)

  ;; Sieve of Eratosthenes up to limit
  ;; Returns total count of prime numbers found
  (func $sieve (export "sieve") (param $limit i32) (result i32)
    (local $i i32)
    (local $j i32)
    (local $count i32)

    ;; Initialize memory to 0
    (local.set $i (i32.const 2))
    (block $outer_break
      (loop $outer_loop
        (br_if $outer_break (i32.gt_s (local.get $i) (local.get $limit)))

        ;; If memory[i] == 0, then i is prime
        (if (i32.eqz (i32.load8_u (local.get $i)))
          (then
            (local.set $count (i32.add (local.get $count) (i32.const 1)))
            ;; Mark multiples of i as composite (1)
            (local.set $j (i32.mul (local.get $i) (i32.const 2)))
            (block $inner_break
              (loop $inner_loop
                (br_if $inner_break (i32.gt_s (local.get $j) (local.get $limit)))
                (i32.store8 (local.get $j) (i32.const 1))
                (local.set $j (i32.add (local.get $j) (local.get $i)))
                (br $inner_loop)
              )
            )
          )
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $outer_loop)
      )
    )
    local.get $count
  )

  ;; Check if a given number is prime
  (func $is_prime (export "is_prime") (param $n i32) (result i32)
    (if (result i32) (i32.lt_s (local.get $n) (i32.const 2))
      (then (i32.const 0))
      (else
        (i32.eqz (i32.load8_u (local.get $n)))
      )
    )
  )
)
