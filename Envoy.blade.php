@servers(['staging' => 'dev@144.126.254.193']);

@setup
    $repository = 'git@github.com:RobustAgency/manavault-backend.git';
    $branch = 'staging';
    $app_dir = '/var/www/manavault';
    $release = date('Y_m_d_H_i');
    $releases_dir = $app_dir . '/releases';
    $new_release_dir = $releases_dir .'/'. $release;
@endsetup

@story('deploy', ['on' => 'staging'])
    clone_repository
    run_composer
    update_symlinks
    writeable
    migrate
    restart_queues
    cleanup_old_releases
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }}
    git clone --depth 1 --branch {{ $branch }} {{ $repository }} {{ $new_release_dir }}
    cd {{ $new_release_dir }}
    git reset --hard {{ $commit }}
@endtask

@task('writeable')
    echo 'make bootstrap/cache writeable ...'
    cd {{ $new_release_dir }}
    chgrp -R www-data bootstrap/cache
    chmod -R g+w bootstrap/cache
@endtask

@task('migrate')
    echo "migrating database ..."
    cd {{ $new_release_dir }}
    php artisan migrate --force -q
@endtask

@task('run_composer')
    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    composer install --prefer-dist --no-scripts -q -o
@endtask

@task('update_symlinks')
    echo "Linking storage directory"
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo 'Symling storage to public folder'
    cd {{ $new_release_dir }} && php artisan storage:link
@endtask

@task('restart_queues')
    cd {{ $new_release_dir }}
    php artisan queue:restart
@endtask

@task('cleanup_old_releases')
    echo "Cleaning up old releases..."
    cd {{ $releases_dir }}
    find . -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
@endtask
