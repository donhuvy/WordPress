import subprocess
import paramiko
import sys
import os

# Configuration
hostname = "163.61.73.174"
username = "root"
password = "HKD_Registry_2026_Secure!"
registry = "registry.bkit.vn"
compose_dir = "/var/www/wordpress-ketoan"

# Determine path of the WordPress root directory (parent of this script's directory)
script_dir = os.path.dirname(os.path.abspath(__file__))
wordpress_root = os.path.dirname(script_dir)

def get_git_commit_hash(cwd):
    try:
        result = subprocess.run(
            ["git", "rev-parse", "--short=7", "HEAD"],
            cwd=cwd,
            capture_output=True,
            text=True,
            check=True
        )
        return result.stdout.strip()
    except Exception as e:
        print(f"Warning: Could not get git commit hash ({e}), fallback to 'custom'")
        return "custom"

def run_local_command(cmd, cwd=None):
    print(f"\n--- Running Local: {cmd} ---")
    result = subprocess.run(cmd, shell=True, text=True, cwd=cwd)
    if result.returncode != 0:
        print(f"Local command failed with return code {result.returncode}")
        sys.exit(1)

def run_remote_commands():
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        print(f"\nConnecting to remote server {hostname}...")
        client.connect(hostname, username=username, password=password, timeout=30)
        print("Connected successfully.")
        
        commands = [
            f"docker login {registry} -u admin -p HKD_Registry_2026_Secure!",
            f"cd {compose_dir} && docker compose pull",
            f"cd {compose_dir} && docker compose up -d"
        ]
        
        for cmd in commands:
            print(f"\n--- Running Remote: {cmd} ---")
            stdin, stdout, stderr = client.exec_command(cmd)
            out = stdout.read().decode('utf-8')
            err = stderr.read().decode('utf-8')
            if out:
                print(out.strip())
            if err:
                print("stderr/warning:", err.strip())
    except Exception as e:
        print("Remote deployment failed:", e)
        sys.exit(1)
    finally:
        client.close()

def main():
    git_tag = get_git_commit_hash(wordpress_root)
    tagged_image = f"{registry}/library/wordpress:{git_tag}"
    latest_image = f"{registry}/library/wordpress:latest"
    
    print(f"==================================================")
    print(f"Building and Deploying WordPress Docker Image")
    print(f"Git Commit Tag : {git_tag}")
    print(f"Image Tagged   : {tagged_image}")
    print(f"Image Latest   : {latest_image}")
    print(f"Registry       : https://{registry}")
    print(f"==================================================")

    # 1. Local Login
    run_local_command(f"docker login {registry} -u admin -p HKD_Registry_2026_Secure!")
    
    # 2. Local Build with both commit hash tag and latest tag
    run_local_command(f"docker build -t {tagged_image} -t {latest_image} .", cwd=wordpress_root)
    
    # 3. Local Push both tags
    run_local_command(f"docker push {tagged_image}")
    run_local_command(f"docker push {latest_image}")
    
    # 4. Remote Deploy
    run_remote_commands()
    
    print(f"\n==================================================")
    print(f"WORDPRESS DEPLOYMENT COMPLETED SUCCESSFULLY!")
    print(f"Deployed Commit Tag: {git_tag}")
    print(f"==================================================")

if __name__ == "__main__":
    main()
