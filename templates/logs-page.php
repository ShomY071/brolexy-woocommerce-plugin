<div class="wrap">
    <h1>Brolexy Sync Logs</h1>
    
    <div class="brolexy-logs-container">
        <?php if (empty($logs)): ?>
            <p>No logs found.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 10%;">Type</th>
                        <th style="width: 20%;">Title</th>
                        <th style="width: 50%;">Message</th>
                        <th style="width: 20%;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['log_type'] === 'error'): ?>
                                    <span class="log-badge log-error">Error</span>
                                <?php else: ?>
                                    <span class="log-badge log-info">Info</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log['title']); ?></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p style="margin-top: 20px;">
            <em>Showing last 100 log entries</em>
        </p>
    </div>
</div>

<style>
.brolexy-logs-container {
    max-width: 1400px;
}

.log-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.log-error {
    background: #dc3545;
    color: white;
}

.log-info {
    background: #17a2b8;
    color: white;
}
</style>