# FullPageOS Kiosk Master Build Guide: PozarniPoplach.cz
This document contains the exact sequence of commands and configurations required to build a production-ready, auto-updating, and headless Raspberry Pi kiosk from a fresh FullPageOS installation.

## Phase 1: Free Up Network Ports
FullPageOS ships with default web and DNS servers that conflict with the captive portal. We must disable them permanently.

```bash
# Stop and disable the default web server (frees up Port 80)
sudo systemctl stop lighttpd
sudo systemctl disable lighttpd

# Stop and disable the default DNS server (frees up Port 53)
sudo systemctl stop dnsmasq
sudo systemctl disable dnsmasq

# Kill any lingering zombie processes just in case
sudo killall -9 dnsmasq
```

## Phase 2: Install Balena WiFi Connect & UI
Download the correct ARM architecture binary and its accompanying captive portal UI, then place them in standard system directories.

```bash
# Navigate to a temporary folder
cd /tmp

# Download the ARMv7hf binary archive
wget https://github.com/balena-os/wifi-connect/releases/download/v4.4.6/wifi-connect-v4.4.6-linux-armv7hf.tar.gz

# Create an extraction directory and unpack the archive
mkdir wc_extract
tar -xvzf wifi-connect-v4.4.6-linux-armv7hf.tar.gz -C wc_extract

# Move the executable to the system binaries folder
sudo cp wc_extract/wifi-connect /usr/local/sbin/

# Create the UI directory and copy the assets
sudo mkdir -p /usr/local/share/wifi-connect
sudo find wc_extract -type d -name "ui" -exec cp -r {} /usr/local/share/wifi-connect/ \;

# Clean up temporary files
rm -rf wc_extract wifi-connect-v4.4.6-linux-armv7hf.tar.gz
```

### (Optional: Localize the Captive Portal)
To translate the portal to Czech, edit the HTML/JS files located in /usr/local/share/wifi-connect/ui via SFTP before proceeding.

## Phase 3: Create the Auto-Recovery Script
Create the background script that constantly checks for internet access and broadcasts the "PozarniPoplach.cz" setup hotspot if it goes offline.

1. Create the script file:

```bash
sudo nano /usr/local/bin/wifi-check.sh
```
2. Paste the following code:

```bash
#!/bin/bash

# Give the system time to initialize networking on boot
sleep 15 

while true; do
    # Ping Google's DNS to check for outside internet
    ping -c 1 8.8.8.8 > /dev/null 2>&1
    
    if [ $? -ne 0 ]; then
        echo "Network offline. Launching captive portal..."
        # Launch WiFi connect pointing to the UI folder
        /usr/local/sbin/wifi-connect --portal-ssid "PozarniPoplach.cz" --ui-directory /usr/local/share/wifi-connect/ui
    fi
    
    # Check again every 30 seconds
    sleep 30
done
```
*(Save: Ctrl+O, Enter. Exit: Ctrl+X)*

3. Make the script executable:

```bash
sudo chmod +x /usr/local/bin/wifi-check.sh
```

## Phase 4: Configure the Background Service
Set the script to run automatically as a systemd background service.

1. Create the service file:

```bash
sudo nano /etc/systemd/system/wifi-connect.service
```
2. Paste the following configuration:

```ini
[Unit]
Description=WiFi Connect Auto-Recovery
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/bin/wifi-check.sh
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```
*(Save: Ctrl+O, Enter. Exit: Ctrl+X)*

3. Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable wifi-connect.service
sudo systemctl start wifi-connect.service
```

## Phase 5: Configure Kiosk URL & Browser Settings
1. Set the Target URL:

```bash
sudo nano /boot/firmware/fullpageos.txt
```
*Note: If the path does not exist, use `sudo nano /boot/fullpageos.txt`.*

Change the contents of the file to exactly:

```plaintext
https://alarm.pozarnipoplach.cz
```

2. Configure Chromium Flags:
Modify the browser launch script to allow auto-playing audio for alarms, disable Google Translate UI, and completely bypass the cache without using Incognito mode (ensuring LocalStorage authorization remains intact).

```bash
sudo nano /home/pi/scripts/start_chromium_browser
```
*Note: If the path does not exist, use `nano /opt/custompios/scripts/start_chromium_browser`.*

Find the line where the $CHROMIUM_BROWSER is executed with its flags. Add the following parameters directly to that single line (separated by standard spaces, no backslashes):

```plaintext
--autoplay-policy=no-user-gesture-required --disable-features=Translate --lang=cs --disk-cache-dir=/dev/null --disk-cache-size=1

# add this just above chromium-browser line (first one)
# Force HDMI-2 to mirror exactly what is on HDMI-1
xrandr --output HDMI-1 --auto --output HDMI-2 --auto --same-as HDMI-1
```
*(Save: Ctrl+O, Enter. Exit: Ctrl+X)*

3. Restart the Display to Apply:

```bash
sudo systemctl restart lightdm
```

## Phase 6: Prepare for Cloning (Amnesia)
Before pulling the SD card to create the master .img clone, you must wipe the local network credentials so new devices trigger the setup portal on their first boot.

1. Find the saved network name:

```bash
nmcli connection show
```

2. Delete the network:

```bash
sudo nmcli connection delete "YOUR_WIFI_NAME"
```

3. Safely shutdown immediately (Do not reboot!):

```bash
sudo shutdown -h now
```