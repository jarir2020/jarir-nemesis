<h1>Audit Log Details #<?= $audit['id'] ?></h1>
<p><strong>Event:</strong> <?= $audit['event'] ?></p>
<p><strong>Type:</strong> <?= $audit['auditable_type'] ?></p>
<p><strong>ID:</strong> <?= $audit['auditable_id'] ?></p>
<p><strong>User ID:</strong> <?= $audit['user_id'] ?></p>
<p><strong>Time:</strong> <?= $audit['created_at'] ?></p>
<p><strong>URL:</strong> <?= $audit['url'] ?></p>
<p><strong>IP:</strong> <?= $audit['ip_address'] ?></p>
<p><strong>User Agent:</strong> <?= $audit['user_agent'] ?></p>

<h2>Changes</h2>
<table border="1">
    <tr>
        <th>Old Values</th>
        <th>New Values</th>
    </tr>
    <tr>
        <td valign="top"><pre><?= json_encode(json_decode($audit['old_values']), JSON_PRETTY_PRINT) ?></pre></td>
        <td valign="top"><pre><?= json_encode(json_decode($audit['new_values']), JSON_PRETTY_PRINT) ?></pre></td>
    </tr>
</table>
<a href="/audit">Back to List</a>
