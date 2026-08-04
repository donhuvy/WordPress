import os
import sys
import time
import hashlib
import paramiko
from datetime import datetime

HOST = "163.61.73.174"
USER = "root"
PASS = "HKD_Registry_2026_Secure!"
DB_NAME = "wordpress"
DB_USER = "root"
DB_PASS = "HKD_Registry_2026_Secure!"

LOCAL_BACKUP_BASE = r"c:\github\WordPress\backups"

def sha256_file(filepath):
    h = hashlib.sha256()
    with open(filepath, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

def main():
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    backup_dir = os.path.join(LOCAL_BACKUP_BASE, f"ketoan_bkit_vn_{timestamp}")
    os.makedirs(backup_dir, exist_ok=True)
    print(f"[*] Created local backup directory: {backup_dir}")

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    print(f"[*] Connecting to remote server {HOST}...")
    ssh.connect(HOST, username=USER, password=PASS, timeout=30)
    print("[+] Connected successfully via SSH.")

    print("[*] Performing database backup on server...")
    db_cmd = f"mysqldump -u {DB_USER} -p'{DB_PASS}' {DB_NAME} | gzip > /tmp/ketoan_db_backup.sql.gz"
    stdin, stdout, stderr = ssh.exec_command(db_cmd)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        err = stderr.read().decode('utf-8')
        print(f"[-] Host mysqldump failed (exit code {exit_code}): {err}")
        print("[*] Trying fallback via Docker container...")
        db_cmd_fallback = f"docker exec wordpress-ketoan mysqldump -u {DB_USER} -p'{DB_PASS}' {DB_NAME} | gzip > /tmp/ketoan_db_backup.sql.gz"
        stdin, stdout, stderr = ssh.exec_command(db_cmd_fallback)
        exit_code = stdout.channel.recv_exit_status()
        if exit_code != 0:
            sys.exit(f"[-] Fallback DB backup failed: {stderr.read().decode('utf-8')}")
    print("[+] Database exported and compressed to /tmp/ketoan_db_backup.sql.gz")

    print("[*] Archiving media and wp-content files...")
    files_cmd = "docker run --rm -v wordpress-ketoan_wordpress_data:/var/www/html -v /tmp:/backup alpine tar czf /backup/ketoan_wpcontent_backup.tar.gz -C /var/www/html wp-content"
    stdin, stdout, stderr = ssh.exec_command(files_cmd)
    exit_code = stdout.channel.recv_exit_status()
    if exit_code != 0:
        err = stderr.read().decode('utf-8')
        sys.exit(f"[-] Media backup failed: {err}")
    print("[+] wp-content archived to /tmp/ketoan_wpcontent_backup.tar.gz")

    sftp = ssh.open_sftp()
    
    local_db_path = os.path.join(backup_dir, "ketoan_db_backup.sql.gz")
    print(f"[*] Downloading database backup to {local_db_path}...")
    sftp.get("/tmp/ketoan_db_backup.sql.gz", local_db_path)
    db_size = os.path.getsize(local_db_path)
    print(f"[+] Downloaded database backup ({db_size / (1024*1024):.2f} MB).")

    local_files_path = os.path.join(backup_dir, "ketoan_wpcontent_backup.tar.gz")
    print(f"[*] Downloading wp-content backup to {local_files_path}...")
    sftp.get("/tmp/ketoan_wpcontent_backup.tar.gz", local_files_path)
    files_size = os.path.getsize(local_files_path)
    print(f"[+] Downloaded wp-content backup ({files_size / (1024*1024):.2f} MB).")

    sftp.close()

    print("[*] Cleaning up temporary files on remote server...")
    ssh.exec_command("rm -f /tmp/ketoan_db_backup.sql.gz /tmp/ketoan_wpcontent_backup.tar.gz")
    ssh.close()
    print("[+] Remote cleanup completed.")

    print("[*] Generating SHA-256 checksums...")
    db_hash = sha256_file(local_db_path)
    files_hash = sha256_file(local_files_path)

    checksum_path = os.path.join(backup_dir, "checksums.sha256")
    with open(checksum_path, "w", encoding="utf-8") as f:
        f.write(f"{db_hash}  ketoan_db_backup.sql.gz\n")
        f.write(f"{files_hash}  ketoan_wpcontent_backup.tar.gz\n")
    print(f"[+] Checksums generated and written to {checksum_path}")

    print("\n==========================================")
    print("      BACKUP COMPLETED SUCCESSFULLY      ")
    print("==========================================")
    print(f"Location: {backup_dir}")
    print(f"Database: ketoan_db_backup.sql.gz ({db_size / (1024*1024):.2f} MB)")
    print(f"Media/Files: ketoan_wpcontent_backup.tar.gz ({files_size / (1024*1024):.2f} MB)")

if __name__ == "__main__":
    main()
