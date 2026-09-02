(module
  ;; Import WASI Preview 1 fd_write
  (import "wasi_snapshot_preview1" "fd_write"
    (func $fd_write (param i32 i32 i32 i32) (result i32)))

  (memory (export "memory") 1)

  ;; Data segment at offset 16: string text
  (data (i32.const 16) "Hello from Zenith WebAssembly WASI Runtime!\n")

  (func $main (export "_start")
    ;; Setup ciovec struct at offset 0:
    ;; [0..3]  = buf pointer (16)
    ;; [4..7]  = buf length (44)
    (i32.store (i32.const 0) (i32.const 16))
    (i32.store (i32.const 4) (i32.const 44))

    ;; Call fd_write:
    ;; fd = 1 (stdout)
    ;; iovs = 0
    ;; iovs_len = 1
    ;; nwritten = 8
    (drop
      (call $fd_write
        (i32.const 1)
        (i32.const 0)
        (i32.const 1)
        (i32.const 8)
      )
    )
  )
)
