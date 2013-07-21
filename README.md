yii-permalink
=============

This extension allows to create human-readable URL's for records in db.
Currently the best we have is routes like `/post/view/5` or `/post/view/5/hello-world`, but with this extension you can reach the same model with url like `/hello-world`, or `/posts/hello-world`. All you need is to do some setup and create permalinks.

Imagine if post with id of 1 has permalink `/hello-world`.

    <?php echo CHtml::link('My post', array('post/view', 'id' => 1)) ?>

This code will produce following: `<a href="/hello-world">My post</a>`
So creating links is completely transparent. Disabling permalinks doesn't crash the system.

Installation
============

The core component is the PermalinkManager and it needs to be accessible as application component:

    'components' => array(
    ...
    'permalinkManager' => array('class' => 'ext.yii-permalink.PermalinkManager'),
    ...
    ),

PermalinkRule is responsible for parsing and creating URL's. Include it in config:

    'components' => array(
        ...
        'urlManager' => array(
            'rules' => array(
                ...
                array(
                    'class' => 'ext.yii-permalink.PermalinkRule',
                ),
            ),
        ),
        ...
    ),

Now we need to attach permalink to a model. We can do it several ways. Like so:

    $post = new Post;
    $post->title = 'Hello, World!';
    // initialize some fields

    $post->save();

    Yii::app()->permalinkManager->setPermalink($post, Permalink::make($post->title));

So basically, after saving post we assigning a permalink to based on it's title. We use here `Permalink::make` to remove invalid chars from it (a-z, 0-9, -, _, / are valid characters).

Or we can attach a behavior to a model:

    class User extends CActiveRecord {
        ...
        function behaviors() {
            return array(
                'permalinkBehavior' => 'ext.yii-permalink.PermalinkBehavior',
            );
        }
        ...
    }

This behavior will automatically validate if permalink is exists and if not, assign it after record is successfully saved.

Custom routes
=============

By default, permalinks are rodirected to `<model class>/view`. So for Post it would be `post/view`. But sometimes we need to override this. For example, if Post model is part of PostModule, the route would be something like `post/default/view`. To change default route we can specify an entry in PermalinkRule::map. This is an array of `ModelClassName => Route` entires. In our case we would add an entry `'Post' => 'post/default/view'`