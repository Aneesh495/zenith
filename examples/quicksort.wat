(module
  (memory (export "memory") 4)

  ;; Store element at array[index]
  (func $set (export "set") (param $idx i32) (param $val i32)
    (i32.store (i32.mul (local.get $idx) (i32.const 4)) (local.get $val))
  )

  ;; Load element from array[index]
  (func $get (export "get") (param $idx i32) (result i32)
    (i32.load (i32.mul (local.get $idx) (i32.const 4)))
  )

  ;; Swap elements at indices i and j
  (func $swap (param $i i32) (param $j i32)
    (local $addr_i i32)
    (local $addr_j i32)
    (local $tmp_i i32)
    (local $tmp_j i32)
    (local.set $addr_i (i32.mul (local.get $i) (i32.const 4)))
    (local.set $addr_j (i32.mul (local.get $j) (i32.const 4)))
    (local.set $tmp_i (i32.load (local.get $addr_i)))
    (local.set $tmp_j (i32.load (local.get $addr_j)))
    (i32.store (local.get $addr_i) (local.get $tmp_j))
    (i32.store (local.get $addr_j) (local.get $tmp_i))
  )

  ;; Partition array around pivot using Lomuto scheme
  (func $partition (param $low i32) (param $high i32) (result i32)
    (local $pivot i32)
    (local $i i32)
    (local $j i32)
    (local $val i32)

    (local.set $pivot (i32.load (i32.mul (local.get $high) (i32.const 4))))
    (local.set $i (local.get $low))
    (local.set $j (local.get $low))

    (block $exit
      (loop $loop
        (br_if $exit (i32.ge_s (local.get $j) (local.get $high)))
        (local.set $val (i32.load (i32.mul (local.get $j) (i32.const 4))))
        (if (i32.le_s (local.get $val) (local.get $pivot))
          (then
            (call $swap (local.get $i) (local.get $j))
            (local.set $i (i32.add (local.get $i) (i32.const 1)))
          )
        )
        (local.set $j (i32.add (local.get $j) (i32.const 1)))
        (br $loop)
      )
    )
    (call $swap (local.get $i) (local.get $high))
    local.get $i
  )

  ;; Recursive quicksort routine
  (func $quicksort (export "quicksort") (param $low i32) (param $high i32)
    (local $p i32)
    (if (i32.lt_s (local.get $low) (local.get $high))
      (then
        (local.set $p (call $partition (local.get $low) (local.get $high)))
        (call $quicksort (local.get $low) (i32.sub (local.get $p) (i32.const 1)))
        (call $quicksort (i32.add (local.get $p) (i32.const 1)) (local.get $high))
      )
    )
  )

  ;; Populate memory with N pseudo-random numbers using LCG
  (func $populate (export "populate") (param $count i32) (param $seed i32)
    (local $i i32)
    (local $state i32)
    (local.set $state (local.get $seed))
    (local.set $i (i32.const 0))
    (block $exit
      (loop $loop
        (br_if $exit (i32.ge_s (local.get $i) (local.get $count)))
        ;; LCG: state = (state * 1664525 + 1013904223) & 0x7FFFFFFF
        (local.set $state (i32.add (i32.mul (local.get $state) (i32.const 1664525)) (i32.const 1013904223)))
        (call $set (local.get $i) (i32.and (local.get $state) (i32.const 2147483647)))
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $loop)
      )
    )
  )

  ;; Check if array is sorted: returns 1 if sorted, 0 otherwise
  (func $is_sorted (export "is_sorted") (param $count i32) (result i32)
    (local $i i32)
    (local $a i32)
    (local $b i32)
    (local.set $i (i32.const 1))
    (block $exit
      (loop $loop
        (br_if $exit (i32.ge_s (local.get $i) (local.get $count)))
        (local.set $a (call $get (i32.sub (local.get $i) (i32.const 1))))
        (local.set $b (call $get (local.get $i)))
        (if (i32.gt_s (local.get $a) (local.get $b))
          (then (return (i32.const 0)))
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $loop)
      )
    )
    (i32.const 1)
  )
)
