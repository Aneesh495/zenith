(module
  ;; Recursive Fibonacci implementation
  (func $fib_recursive (export "fib_recursive") (param $n i32) (result i32)
    (if (result i32) (i32.le_s (local.get $n) (i32.const 1))
      (then (local.get $n))
      (else
        (i32.add
          (call $fib_recursive (i32.sub (local.get $n) (i32.const 1)))
          (call $fib_recursive (i32.sub (local.get $n) (i32.const 2)))
        )
      )
    )
  )

  ;; Iterative Fibonacci implementation with O(N) complexity
  (func $fib_iterative (export "fib_iterative") (param $n i32) (result i32)
    (local $a i32)
    (local $b i32)
    (local $temp i32)
    (local $i i32)

    (if (result i32) (i32.le_s (local.get $n) (i32.const 1))
      (then (local.get $n))
      (else
        (local.set $a (i32.const 0))
        (local.set $b (i32.const 1))
        (local.set $i (i32.const 2))
        (block $exit
          (loop $loop
            (br_if $exit (i32.gt_s (local.get $i) (local.get $n)))
            (local.set $temp (i32.add (local.get $a) (local.get $b)))
            (local.set $a (local.get $b))
            (local.set $b (local.get $temp))
            (local.set $i (i32.add (local.get $i) (i32.const 1)))
            (br $loop)
          )
        )
        local.get $b
      )
    )
  )
)
