(module
  ;; Import WASI fd_write
  (import "wasi_snapshot_preview1" "fd_write"
    (func $fd_write (param i32 i32 i32 i32) (result i32)))

  (memory (export "memory") 1)

  ;; Character palette for escape iterations: " .:-=+*#%@"
  (data (i32.const 100) " .:-=+*#%@")

  (func $main (export "_start")
    (local $py i32)
    (local $px i32)
    (local $x0 f64)
    (local $y0 f64)
    (local $x f64)
    (local $y f64)
    (local $x2 f64)
    (local $y2 f64)
    (local $iter i32)
    (local $char_idx i32)
    (local $char_byte i32)

    ;; Setup ciovec at address 0:
    ;; [0..3] = buffer pointer (200)
    ;; [4..7] = buffer length (61) (60 chars + newline)
    (i32.store (i32.const 0) (i32.const 200))
    (i32.store (i32.const 4) (i32.const 61))

    ;; Outer loop: py from 0 to 24 (25 rows)
    (local.set $py (i32.const 0))
    (block $b_py
      (loop $l_py
        (br_if $b_py (i32.ge_s (local.get $py) (i32.const 25)))

        ;; y0 = -1.2 + (py * 0.1)
        (local.set $y0
          (f64.add
            (f64.const -1.2)
            (f64.mul (f64.convert_i32_s (local.get $py)) (f64.const 0.1))
          )
        )

        ;; Inner loop: px from 0 to 59 (60 cols)
        (local.set $px (i32.const 0))
        (block $b_px
          (loop $l_px
            (br_if $b_px (i32.ge_s (local.get $px) (i32.const 60)))

            ;; x0 = -2.0 + (px * 0.045)
            (local.set $x0
              (f64.add
                (f64.const -2.0)
                (f64.mul (f64.convert_i32_s (local.get $px)) (f64.const 0.045))
              )
            )

            ;; Mandelbrot escape loop
            (local.set $x (f64.const 0.0))
            (local.set $y (f64.const 0.0))
            (local.set $iter (i32.const 0))

            (block $b_iter
              (loop $l_iter
                (br_if $b_iter (i32.ge_s (local.get $iter) (i32.const 30)))

                (local.set $x2 (f64.mul (local.get $x) (local.get $x)))
                (local.set $y2 (f64.mul (local.get $y) (local.get $y)))

                ;; If x^2 + y^2 > 4.0, escaped
                (br_if $b_iter (f64.gt (f64.add (local.get $x2) (local.get $y2)) (f64.const 4.0)))

                ;; y = 2 * x * y + y0
                (local.set $y
                  (f64.add
                    (f64.mul (f64.const 2.0) (f64.mul (local.get $x) (local.get $y)))
                    (local.get $y0)
                  )
                )

                ;; x = x^2 - y^2 + x0
                (local.set $x
                  (f64.add
                    (f64.sub (local.get $x2) (local.get $y2))
                    (local.get $x0)
                  )
                )

                (local.set $iter (i32.add (local.get $iter) (i32.const 1)))
                (br $l_iter)
              )
            )

            ;; Select palette character index: min(iter / 3, 9)
            (local.set $char_idx (i32.div_u (local.get $iter) (i32.const 3)))
            (if (i32.ge_u (local.get $char_idx) (i32.const 10))
              (then (local.set $char_idx (i32.const 9)))
            )

            (local.set $char_byte (i32.load8_u (i32.add (i32.const 100) (local.get $char_idx))))
            (i32.store8 (i32.add (i32.const 200) (local.get $px)) (local.get $char_byte))

            (local.set $px (i32.add (local.get $px) (i32.const 1)))
            (br $l_px)
          )
        )

        ;; Append newline character (10) at index 260 (200 + 60)
        (i32.store8 (i32.const 260) (i32.const 10))

        ;; Write line buffer to stdout via WASI fd_write
        (drop
          (call $fd_write
            (i32.const 1)  ;; stdout
            (i32.const 0)  ;; ciovec pointer
            (i32.const 1)  ;; 1 iovec
            (i32.const 8)  ;; nwritten return pointer
          )
        )

        (local.set $py (i32.add (local.get $py) (i32.const 1)))
        (br $l_py)
      )
    )
  )
)
