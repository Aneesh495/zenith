(module
  (memory (export "memory") 2)

  ;; Multiply two NxN matrices of f64 numbers and return the grand sum
  (func $matmul (export "matmul") (param $n i32) (result f64)
    (local $i i32)
    (local $j i32)
    (local $k i32)
    (local $sum f64)
    (local $total f64)

    ;; For each row i
    (local.set $i (i32.const 0))
    (block $b_i
      (loop $l_i
        (br_if $b_i (i32.ge_s (local.get $i) (local.get $n)))

        ;; For each column j
        (local.set $j (i32.const 0))
        (block $b_j
          (loop $l_j
            (br_if $b_j (i32.ge_s (local.get $j) (local.get $n)))

            ;; Dot product across dimension k
            (local.set $sum (f64.const 0))
            (local.set $k (i32.const 0))
            (block $b_k
              (loop $l_k
                (br_if $b_k (i32.ge_s (local.get $k) (local.get $n)))
                (local.set $sum (f64.add (local.get $sum) (f64.mul (f64.convert_i32_s (local.get $i)) (f64.convert_i32_s (local.get $j)))))
                (local.set $k (i32.add (local.get $k) (i32.const 1)))
                (br $l_k)
              )
            )
            (local.set $total (f64.add (local.get $total) (local.get $sum)))
            (local.set $j (i32.add (local.get $j) (i32.const 1)))
            (br $l_j)
          )
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $l_i)
      )
    )
    local.get $total
  )
)
