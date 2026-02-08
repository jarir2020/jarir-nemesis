<h1>Audit Logs</h1>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>ID</th>
            <th>Event</th>
            <th>Type</th>
            <th>ID</th>
            <th>User</th>
            <th>IP</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($audits as $audit): ?>
        <tr>
            <td><?= $audit['id'] ?></td>
            <td><?= $audit['event'] ?></td>
            <td><?= $audit['auditable_type'] ?></td>
            <td><?= $audit['auditable_id'] ?></td>
            <td><?= $audit['user_id'] ?></td>
            <td><?= $audit['ip_address'] ?></td>
            <td><?= $audit['created_at'] ?></td>
            <td><a href="/audit/<?= $audit['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
