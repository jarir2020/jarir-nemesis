<?php

/**
 * A helper file for your IDE to provide autocomplete information.
 * @author Jarir Ahmed
 */

namespace App\Models {
    /**
     * @property int $id
     * @property int $serial
     * @property string $application_number
     * @property string $registration_number
     * @property string $name_of_worker
     * @property string $document_number
     * @property string $status
     * @property string $employer_identification
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\Applications find(mixed $id)
     * @method static App\Models\Applications create(array $attributes)
     */
    class Applications extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $post_id
     * @property int $user_id
     * @property string $body
     * @property string $created_at
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\Comment find(mixed $id)
     * @method static App\Models\Comment create(array $attributes)
     */
    class Comment extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $user_id
     * @property string $title
     * @property string $content
     * @property string $status
     * @property string $deleted_at
     * @property string $created_at
     * @property string $updated_at
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\Post find(mixed $id)
     * @method static App\Models\Post create(array $attributes)
     */
    class Post extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property string $name
     * @property float $price
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\Product find(mixed $id)
     * @method static App\Models\Product create(array $attributes)
     */
    class Product extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property string $name
     * @property string $created_at
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\Tag find(mixed $id)
     * @method static App\Models\Tag create(array $attributes)
     */
    class Tag extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property string $name
     * @property string $email
     * @property string $password
     * @property string $auth_token
     * @property string $otp
     * @property string $totp_secret
     * @property string $verification_token
     * @property string $email_verified_at
     * @property string $created_at
     * @property string $updated_at
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\User find(mixed $id)
     * @method static App\Models\User create(array $attributes)
     */
    class User extends \Nemesis\Core\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property string $name
     * @property string $email
     * @property string $password
     * @property string $auth_token
     * @property string $otp
     * @property string $totp_secret
     * @property string $verification_token
     * @property string $email_verified_at
     * @property string $created_at
     * @property string $updated_at
     * @method static \Nemesis\Database\Builder query()
     * @method static App\Models\UserModel find(mixed $id)
     * @method static App\Models\UserModel create(array $attributes)
     */
    class UserModel extends \Nemesis\Core\Model {}
}

