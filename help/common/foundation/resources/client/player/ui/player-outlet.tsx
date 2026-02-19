import {usePlayerStore} from '@common/player/hooks/use-player-store';
import {PlayerStoreContext} from '@common/player/player-context';
import {HtmlAudioProvider} from '@common/player/providers/html-audio-provider';
import {HtmlVideoProvider} from '@common/player/providers/html-video-provider';
import {YoutubeProvider} from '@common/player/providers/youtube/youtube-provider';
import React, {memo, Suspense, useContext, useEffect} from 'react';

const HlsProvider = React.lazy(
  () => import('@common/player/providers/hls-provider'),
);
const DashProvider = React.lazy(
  () => import('@common/player/providers/dash-provider'),
);

interface Props {
  className?: string;
}
export const PlayerOutlet = memo(({className}: Props) => {
  const {getState} = useContext(PlayerStoreContext);

  useEffect(() => {
    getState().init();
    return getState().destroy;
  }, [getState]);

  return (
    <div className={className}>
      <Provider />
    </div>
  );
});

function Provider() {
  const provider = usePlayerStore(s => s.providerName);
  switch (provider) {
    case 'youtube':
      return <YoutubeProvider />;
    case 'htmlVideo':
      return <HtmlVideoProvider />;
    case 'htmlAudio':
    case 'htmlAudioBlob':
      return <HtmlAudioProvider />;
    case 'hls':
      return (
        <Suspense>
          <HlsProvider />
        </Suspense>
      );
    case 'dash':
      return (
        <Suspense>
          <DashProvider />
        </Suspense>
      );
    default:
      return null;
  }
}
