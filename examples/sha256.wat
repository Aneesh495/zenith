(module
  (memory (export "memory") 2)

  ;; Bitwise rotation right: rotr(x, n)
  (func $rotr (param $x i32) (param $n i32) (result i32)
    (i32.rotr (local.get $x) (local.get $n))
  )

  ;; SHA-256 Choose: (x & y) ^ (~x & z)
  (func $ch (param $x i32) (param $y i32) (param $z i32) (result i32)
    (i32.xor
      (i32.and (local.get $x) (local.get $y))
      (i32.and (i32.xor (local.get $x) (i32.const -1)) (local.get $z))
    )
  )

  ;; SHA-256 Majority: (x & y) ^ (x & z) ^ (y & z)
  (func $maj (param $x i32) (param $y i32) (param $z i32) (result i32)
    (i32.xor
      (i32.xor
        (i32.and (local.get $x) (local.get $y))
        (i32.and (local.get $x) (local.get $z))
      )
      (i32.and (local.get $y) (local.get $z))
    )
  )

  ;; Sigma 0: rotr(x, 2) ^ rotr(x, 13) ^ rotr(x, 22)
  (func $sig0 (param $x i32) (result i32)
    (i32.xor
      (i32.xor
        (i32.rotr (local.get $x) (i32.const 2))
        (i32.rotr (local.get $x) (i32.const 13))
      )
      (i32.rotr (local.get $x) (i32.const 22))
    )
  )

  ;; Sigma 1: rotr(x, 6) ^ rotr(x, 11) ^ rotr(x, 25)
  (func $sig1 (param $x i32) (result i32)
    (i32.xor
      (i32.xor
        (i32.rotr (local.get $x) (i32.const 6))
        (i32.rotr (local.get $x) (i32.const 11))
      )
      (i32.rotr (local.get $x) (i32.const 25))
    )
  )

  ;; Benchmark compression loop: performs $rounds iterations of SHA-256 step transforms
  (func $sha256_compress (export "sha256_compress") (param $rounds i32) (result i32)
    (local $i i32)
    (local $a i32)
    (local $b i32)
    (local $c i32)
    (local $d i32)
    (local $e i32)
    (local $f i32)
    (local $g i32)
    (local $h i32)
    (local $t1 i32)
    (local $t2 i32)

    ;; Initialize state with SHA-256 standard initialization constants
    (local.set $a (i32.const 1779033703))
    (local.set $b (i32.const -1150833019))
    (local.set $c (i32.const 1013904242))
    (local.set $d (i32.const -1521486534))
    (local.set $e (i32.const 1359893119))
    (local.set $f (i32.const -1694144372))
    (local.set $g (i32.const 528734635))
    (local.set $h (i32.const 1541459225))

    (local.set $i (i32.const 0))
    (block $exit
      (loop $loop
        (br_if $exit (i32.ge_s (local.get $i) (local.get $rounds)))

        ;; t1 = h + sig1(e) + ch(e, f, g) + 0x428A2F98 + i
        (local.set $t1
          (i32.add
            (i32.add
              (i32.add (local.get $h) (call $sig1 (local.get $e)))
              (call $ch (local.get $e) (local.get $f) (local.get $g))
            )
            (i32.add (i32.const 1116352408) (local.get $i))
          )
        )

        ;; t2 = sig0(a) + maj(a, b, c)
        (local.set $t2
          (i32.add
            (call $sig0 (local.get $a))
            (call $maj (local.get $a) (local.get $b) (local.get $c))
          )
        )

        (local.set $h (local.get $g))
        (local.set $g (local.get $f))
        (local.set $f (local.get $e))
        (local.set $e (i32.add (local.get $d) (local.get $t1)))
        (local.set $d (local.get $c))
        (local.set $c (local.get $b))
        (local.set $b (local.get $a))
        (local.set $a (i32.add (local.get $t1) (local.get $t2)))

        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $loop)
      )
    )

    ;; Return state a XOR state e
    (i32.xor (local.get $a) (local.get $e))
  )
)
