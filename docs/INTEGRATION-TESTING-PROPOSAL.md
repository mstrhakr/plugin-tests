# Integration Testing with VM Orchestration

> **Status:** Proposal / Future Feature  
> **Author:** mstrhakr  
> **Date:** February 2026

## Overview

This document outlines a potential approach for running integration tests against a real Unraid environment using VM orchestration. The key insight is that we can **orchestrate** a user-provided Unraid VM without needing to distribute or emulate Unraid itself.

## The Problem

Unit tests with mocks (what plugin-tests currently provides) are excellent for testing business logic, but they can't verify:

- Real Docker daemon interactions
- Actual file permissions and paths
- WebUI rendering
- Network behavior
- Plugin installation/upgrade flows

Manual testing catches these, but it's:
- Time-consuming
- Not repeatable
- Can't run in CI/CD
- Risks corrupting your dev environment

## Proposed Solution

Use hypervisor APIs to orchestrate a user-owned Unraid VM:

```
┌─────────────────────────────────────────────────────────────────────┐
│  Developer's Machine / CI Runner                                     │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  plugin-tests orchestrator                                   │    │
│  │                                                              │    │
│  │  1. Call hypervisor API → Start VM                          │    │
│  │  2. Call hypervisor API → Create/verify snapshot            │    │
│  │  3. SSH to Unraid → Upload plugin, run PHPUnit              │    │
│  │  4. Collect results                                          │    │
│  │  5. Call hypervisor API → Restore snapshot                  │    │
│  │  6. Call hypervisor API → Stop VM (optional)                │    │
│  └─────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────────┬─┘
                                                                    │
                                                                    │ Hypervisor API
                                                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Hypervisor (User's own infrastructure)                             │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Unraid VM                                                   │    │
│  │  - User's own license (trial or purchased)                   │    │
│  │  - Pre-configured test state                                 │    │
│  │  - Docker, shares, sample plugins ready                      │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

## Why This Works

**We're orchestrating, not emulating:**

- ❌ We don't package or distribute Unraid
- ❌ We don't spoof licenses or GUIDs
- ❌ We don't redistribute any Lime Technology code
- ✅ User provides their own legal Unraid VM
- ✅ We just automate: start → test → restore

This keeps the approach legally clean while enabling real integration testing.

## Supported Hypervisors

| Hypervisor | API Type | Snapshot Support | Implementation Difficulty |
|------------|----------|------------------|---------------------------|
| **Proxmox** | REST API | ✅ Excellent | Easy |
| **ESXi/vSphere** | REST API | ✅ Excellent | Medium |
| **libvirt/QEMU** | virsh CLI | ✅ Good | Easy |
| **Hyper-V** | PowerShell | ✅ Good | Medium |
| **VirtualBox** | VBoxManage | ✅ Good | Easy |
| **Unraid VM Manager** | virsh CLI | ✅ Good | Easy (meta!) |

## Proposed Configuration

```yaml
# .plugin-tests.remote.yml (gitignored - contains credentials)
integration:
  hypervisor:
    type: proxmox  # proxmox | esxi | libvirt | hyperv | virtualbox
    host: 192.168.1.100
    port: 8006
    user: root@pam
    token_id: plugin-tests
    token_secret: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    verify_ssl: false
    
  vm:
    id: 105  # VM ID or name
    snapshot_name: clean-state  # Snapshot to restore after tests
    boot_timeout: 120  # Seconds to wait for VM boot
    
  unraid:
    host: 192.168.1.105  # VM's IP when running
    ssh_user: root
    ssh_key: ~/.ssh/unraid_test_key
    plugin_path: /usr/local/emhttp/plugins/my-plugin
```

## Proposed Workflow

```bash
# One-time setup
./vendor/bin/plugin-tests integration:setup
# Interactive wizard creates .plugin-tests.remote.yml

# Run integration tests
./vendor/bin/plugin-tests integration:run ./tests/integration

# With options
./vendor/bin/plugin-tests integration:run ./tests/integration \
    --fresh-snapshot \
    --stop-after \
    --filter="testDockerOperations"
```

### Execution Flow

```
1. START VM (if not running)
   └─► Hypervisor API: Start VM
   └─► Wait for SSH/guest agent ready

2. VERIFY SNAPSHOT EXISTS
   └─► Hypervisor API: List snapshots
   └─► Ensure "clean-state" snapshot exists

3. SYNC FILES
   └─► rsync plugin source to VM
   └─► rsync test files to VM

4. RUN TESTS
   └─► SSH: php /path/to/phpunit.phar
   └─► Stream output, capture results

5. RESTORE SNAPSHOT (always, even on failure)
   └─► Hypervisor API: Rollback to "clean-state"
   └─► VM instantly reverts to pre-test state

6. RETURN RESULTS
   └─► Parse PHPUnit output
   └─► Report pass/fail
```

## Advantages

| Benefit | Description |
|---------|-------------|
| **Real environment** | Tests run on actual Unraid with real PHP, Docker, paths |
| **Destructive tests OK** | Snapshot restore means you can test anything |
| **Repeatable** | Same clean state every time |
| **CI-compatible** | Works with self-hosted GitHub Actions runners |
| **User's own license** | No legal concerns |
| **Any hypervisor** | Abstraction layer supports multiple platforms |

## Limitations

| Limitation | Impact | Mitigation |
|------------|--------|------------|
| Requires VM setup | One-time effort | Provide detailed setup guide |
| Slower than unit tests | 2-5 minutes per run | Use for integration tests only |
| Needs hypervisor API | Some learning curve | Setup wizard, good docs |
| SSH root access to VM | Security consideration | Dedicated test VM, not production |
| Network dependency | Can't run offline | Local network sufficient |

## Testing Pyramid with Integration Tests

```
                    /\
                   /  \
                  / VM \         ← Integration tests (this proposal)
                 / INTE \           Real Unraid, real Docker
                / GRATION\          Slow but comprehensive
               /----------\
              /            \
             /    MOCKED    \    ← Unit tests (current plugin-tests)
            /   UNIT TESTS   \      Fast, CI-friendly
           /                  \     Covers business logic
          /____________________\
```

**Recommended usage:**
- Unit tests: Run on every commit (fast, mocked)
- Integration tests: Run before release, or on PR merge (slow, real)

## Implementation Phases

### Phase 1: Core Infrastructure
- [ ] `HypervisorInterface` - Abstract interface for VM operations
- [ ] `ProxmoxClient` - First implementation (most common in homelab)
- [ ] `SSHConnection` - File sync and command execution
- [ ] Configuration loader and validator

### Phase 2: CLI & UX
- [ ] `integration:setup` - Interactive configuration wizard
- [ ] `integration:run` - Test execution command
- [ ] `integration:status` - Check VM state
- [ ] Progress output and result formatting

### Phase 3: Additional Hypervisors
- [ ] `LibvirtClient` - For QEMU/KVM users
- [ ] `VirtualBoxClient` - For desktop virtualization
- [ ] `HyperVClient` - For Windows hosts
- [ ] `ESXiClient` - For enterprise VMware

### Phase 4: Advanced Features
- [ ] Parallel test execution
- [ ] Test result caching
- [ ] Automatic snapshot management
- [ ] CI/CD examples and templates

## User Setup Requirements

To use integration testing, developers would need:

1. **Unraid VM** - Running on any supported hypervisor
   - Can use 30-day trial or purchased license
   - Configured with desired test state (Docker containers, shares, etc.)
   
2. **Clean snapshot** - A snapshot of the VM in a known-good state
   - Created after initial setup
   - Will be restored after every test run
   
3. **API access** - Credentials for hypervisor API
   - Proxmox: API token with VM.* permissions
   - Others: Equivalent access level
   
4. **SSH access** - Key-based auth to the Unraid VM
   - For file sync and test execution

## Alternatives Considered

### SSH-only (no VM orchestration)
- **Pro:** Simpler, no hypervisor dependency
- **Con:** No snapshot restore, tests can corrupt state
- **Verdict:** Insufficient for destructive testing

### Test runner plugin on Unraid
- **Pro:** HTTP API instead of SSH
- **Con:** Arbitrary code execution risk, complex security model
- **Verdict:** Security concerns outweigh benefits

### Docker-based Unraid
- **Pro:** Would be ideal if possible
- **Con:** Unraid IS the host OS, can't containerize it
- **Verdict:** Not technically feasible

### State provider plugin (read-only)
- **Pro:** Safe, improves mock accuracy
- **Con:** Still testing against mocks, not real system
- **Verdict:** Complementary, not a replacement

## Questions for Future Design

1. Should we support multiple VMs for parallel testing?
2. How do we handle tests that need specific Unraid versions?
3. Should snapshot names be configurable per test suite?
4. Do we need a "warm standby" mode that keeps VM running between runs?
5. How do we handle test fixtures (sample Docker templates, etc.)?

## Contributing

This is a proposal document. If you're interested in implementing any part of this, please:

1. Open an issue to discuss the approach
2. Start with Phase 1 (Proxmox + SSH)
3. Ensure good test coverage of the orchestration code itself
4. Document the user setup process thoroughly

---

*This document represents a design exploration. Implementation priority depends on community interest and contributor availability.*
