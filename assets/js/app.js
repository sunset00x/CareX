/**
 * CarePlus System Master Frontend Logic
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-dismiss Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    })

    // Mark Notification as Read dynamically
    const notifItems = document.querySelectorAll('.notification-item-unread');
    notifItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const notifId = this.dataset.id;
            if (notifId) {
                fetch(`${BASE_URL}ajax/mark-notification-read.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${notifId}`
                }).then(res => res.json())
                  .then(data => {
                      if (data.success) {
                          this.classList.remove('notification-item-unread');
                      }
                  });
            }
        });
    });
});