"""
Post-build hook for the release env: copies firmware.bin into ota/ named
after the running FIRMWARE_VERSION (parsed straight out of src/main.cpp,
so the file name can never drift from what the binary actually reports).
Identical in spirit to the esp32-counter project's script.
"""
import os
import re
import shutil

Import("env")


def copy_versioned_binary(source, target, env):
    project_dir = env["PROJECT_DIR"]
    main_cpp_path = os.path.join(project_dir, "src", "main.cpp")

    with open(main_cpp_path, encoding="utf-8") as f:
        content = f.read()

    match = re.search(r'FIRMWARE_VERSION\s*=\s*"([^"]+)"', content)
    if not match:
        print("[ota] FIRMWARE_VERSION not found in src/main.cpp, skipping ota/ copy.")
        return
    version = match.group(1)

    build_dir = env.subst("$BUILD_DIR")
    src_bin = os.path.join(build_dir, "firmware.bin")
    if not os.path.isfile(src_bin):
        print(f"[ota] {src_bin} not found, skipping ota/ copy.")
        return

    ota_dir = os.path.join(project_dir, "ota")
    os.makedirs(ota_dir, exist_ok=True)
    dest_bin = os.path.join(ota_dir, f"firmware-{version}.bin")
    shutil.copy2(src_bin, dest_bin)
    print(f"[ota] Copied {src_bin} -> {dest_bin}")


env.AddPostAction("$BUILD_DIR/firmware.bin", copy_versioned_binary)
