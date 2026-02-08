<style>
    #nemesis-debugbar {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #2d3748;
        color: #fff;
        font-family: monospace;
        font-size: 12px;
        z-index: 99999;
        display: flex;
        justify-content: space-between;
        padding: 5px 10px;
        box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        cursor: pointer;
    }
    #nemesis-debugbar .section {
        margin-right: 15px;
        display: flex;
        align-items: center;
    }
    #nemesis-debugbar .section strong {
        margin-right: 5px;
        color: #a0aec0;
    }
    #nemesis-debugbar-details {
        display: none;
        position: fixed;
        bottom: 30px;
        left: 0;
        width: 100%;
        max-height: 50%;
        overflow-y: auto;
        background: #1a202c;
        color: #fff;
        padding: 10px;
        z-index: 99998;
        border-top: 1px solid #4a5568;
    }
    #nemesis-debugbar-details table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px; /* Space between tables */
    }
    #nemesis-debugbar-details th, #nemesis-debugbar-details td {
        text-align: left;
        padding: 5px;
        border-bottom: 1px solid #2d3748;
        vertical-align: top; /* Align logs to top */
    }
    #nemesis-debugbar-details th {
        color: #63b3ed;
    }
    #nemesis-debugbar-details .query-time {
        color: #f6ad55;
    }
     #nemesis-debugbar-details h3 {
        color: #fff;
        border-bottom: 1px solid #4a5568;
        padding-bottom: 5px;
        margin-top: 0;
    }
</style>

<div id="nemesis-debugbar" onclick="document.getElementById('nemesis-debugbar-details').style.display = (document.getElementById('nemesis-debugbar-details').style.display === 'block' ? 'none' : 'block')">
    <div style="display:flex;">
        <div class="section">
            <strong>PHP</strong> <?= $data['php_version'] ?>
        </div>
        <div class="section">
            <strong>Time</strong> <?= number_format($data['time']['duration'] * 1000, 2) ?>ms
        </div>
        <div class="section">
            <strong>Memory</strong> <?= number_format($data['memory']['peak'] / 1024 / 1024, 2) ?>MB
        </div>
        <div class="section">
            <strong>Queries</strong> <?= count($data['queries']) ?>
        </div>
        <div class="section">
            <strong>Route</strong> <?= htmlspecialchars($data['request']['method'] . ' ' . $data['request']['uri']) ?>
        </div>
    </div>
    <div>
        <span style="color:#63b3ed;">Nemesis Framework</span>
    </div>
</div>

<div id="nemesis-debugbar-details">
    <div style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <h3>Database Queries (<?= count($data['queries']) ?>)</h3>
            <?php if (empty($data['queries'])): ?>
                <p style="color: #718096;">No queries executed.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">Time</th>
                            <th>Query</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['queries'] as $query): ?>
                        <tr>
                            <td class="query-time"><?= number_format($query['time'] * 1000, 2) ?>ms</td>
                            <td>
                                <div style="color: #90cdf4;"><?= htmlspecialchars($query['query']) ?></div>
                                <?php if (!empty($query['bindings'])): ?>
                                    <div style="color: #718096; font-size: 0.9em;">
                                        Bindings: <?= htmlspecialchars(json_encode($query['bindings'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div style="flex: 1;">
            <h3>Request</h3>
            <table>
                 <tbody>
                    <tr><td><strong>URI</strong></td><td><?= htmlspecialchars($data['request']['uri']) ?></td></tr>
                    <tr><td><strong>Method</strong></td><td><?= htmlspecialchars($data['request']['method']) ?></td></tr>
                    <tr><td><strong>IP</strong></td><td><?= htmlspecialchars($data['request']['ip']) ?></td></tr>
                     <tr><td><strong>Memory Peak</strong></td><td><?= number_format($data['memory']['peak'] / 1024 / 1024, 2) ?> MB</td></tr>
                     <tr><td><strong>Duration</strong></td><td><?= number_format($data['time']['duration'] * 1000, 2) ?> ms</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
