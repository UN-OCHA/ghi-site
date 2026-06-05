(function (Drupal) {

  const LegacyProject = Drupal.GhiPlansLegacyProject;
  const { constants, state } = LegacyProject;

  /**
   * Checks whether a project link participates in the current visible row set.
   */
  const isVisibleProjectLink = (link) => {
    const row = link.closest('tr');
    const element = row || link;
    const style = window.getComputedStyle(element);
    return style.display !== 'none' &&
      style.visibility !== 'hidden' &&
      element.getClientRects().length > 0;
  };

  /**
   * Extracts the data needed to page to a project without a new Ajax request.
   */
  const getProjectDetailLinkData = (link) => {
    const {
      legacyProjectCode,
      legacyProjectId,
      legacyProjectIframeSrc,
      legacyProjectIframeTitle,
      legacyProjectTitle,
    } = link.dataset;
    if (!legacyProjectId || !legacyProjectIframeSrc || !legacyProjectTitle) {
      return null;
    }

    return {
      projectCode: legacyProjectCode || link.textContent.trim(),
      projectId: legacyProjectId,
      iframeSrc: legacyProjectIframeSrc,
      iframeTitle: legacyProjectIframeTitle || legacyProjectTitle,
      title: legacyProjectTitle,
    };
  };

  /**
   * Captures project detail links in the project-count modal's current order.
   */
  LegacyProject.captureProjectDetailPagerContext = (link) => {
    const countDialog = link.closest('.project-count-modal.ui-dialog');
    if (!countDialog) {
      state.projectDetailPagerContext = null;
      return;
    }

    const links = Array.from(
      countDialog.querySelectorAll(
        'a.project-detail-modal[data-legacy-project-id]',
      ),
    ).filter(isVisibleProjectLink);
    const items = links
      .map(getProjectDetailLinkData)
      .filter((item) => item !== null);
    const current = getProjectDetailLinkData(link);
    const currentIndex = current ? items.findIndex((item) => (
      item.projectId === current.projectId
    )) : -1;

    state.projectDetailPagerContext = currentIndex >= 0 ? {
      currentIndex,
      items,
    } : null;
  };

  /**
   * Clears pager context when its project-count modal goes away.
   */
  LegacyProject.clearProjectDetailPagerContext = () => {
    state.projectDetailPagerContext = null;
  };

  /**
   * Creates one Material Icons pager button with an accessible label.
   */
  const createProjectDetailPagerButton = (direction, icon, label) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'legacy-project-pager__button';
    button.dataset.legacyProjectPagerDirection = direction;
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);

    const iconElement = document.createElement('span');
    iconElement.className = 'material-icons';
    iconElement.setAttribute('aria-hidden', 'true');
    iconElement.textContent = icon;
    button.appendChild(iconElement);

    return button;
  };

  /**
   * Adds the pager navigation above the project iframe when needed.
   */
  const ensureProjectDetailPager = (dialog) => {
    const content = dialog.querySelector('.ui-dialog-content');
    if (!content) {
      return null;
    }

    let pager = content.querySelector('.legacy-project-pager');
    if (pager) {
      return pager;
    }

    pager = document.createElement('nav');
    pager.className = 'legacy-project-pager';
    pager.setAttribute('aria-label', Drupal.t('Project navigation'));
    pager.appendChild(
      createProjectDetailPagerButton(
        'previous',
        'keyboard_arrow_left',
        Drupal.t('Previous project'),
      ),
    );
    pager.appendChild(
      createProjectDetailPagerButton(
        'next',
        'keyboard_arrow_right',
        Drupal.t('Next project'),
      ),
    );

    const status = document.createElement('span');
    status.className = 'legacy-project-pager__status';
    status.setAttribute('aria-live', 'polite');
    pager.appendChild(status);

    const iframeWrapper = content.querySelector('.legacy-project-iframe-wrapper');
    if (iframeWrapper) {
      content.insertBefore(pager, iframeWrapper);
    }
    else {
      content.prepend(pager);
    }

    return pager;
  };

  /**
   * Removes hidden staging iframes that never became the active project.
   */
  const removeProjectDetailStagingIframes = (dialog, keep = null) => {
    dialog
      .querySelectorAll('iframe.legacy-project-iframe--staging')
      .forEach((iframe) => {
        if (iframe !== keep) {
          iframe.remove();
        }
      });
  };

  /**
   * Removes stale sibling preloads while preserving requested project ids.
   */
  const removeProjectDetailPreloadIframes = (
    dialog,
    keepProjectIds = new Set(),
  ) => {
    dialog
      .querySelectorAll('iframe.legacy-project-iframe--preload')
      .forEach((iframe) => {
        if (!keepProjectIds.has(iframe.dataset.legacyProjectId)) {
          iframe.remove();
        }
      });
  };

  /**
   * Removes pager markup from a detail dialog without affecting the title.
   */
  const removeProjectDetailPager = (dialog) => {
    dialog.querySelector('.legacy-project-pager')?.remove();
    removeProjectDetailStagingIframes(dialog);
    removeProjectDetailPreloadIframes(dialog);
    delete dialog.dataset.legacyProjectPagerCurrentIndex;
    delete dialog.dataset.legacyProjectPagerLoading;
    delete dialog.dataset.legacyProjectPagerLoadToken;
    dialog.querySelector('.legacy-project-iframe-wrapper')?.removeAttribute('aria-busy');
  };

  /**
   * Returns TRUE when the current pager state can page to the given index.
   */
  const hasProjectDetailPagerIndex = (index) => (
    state.projectDetailPagerContext &&
    index >= 0 &&
    index < state.projectDetailPagerContext.items.length
  );

  /**
   * Updates the pager buttons and visible "current / total" status.
   */
  const refreshProjectDetailPager = (dialog) => {
    if (
      !state.projectDetailPagerContext ||
      state.projectDetailPagerContext.items.length < 2
    ) {
      removeProjectDetailPager(dialog);
      return;
    }

    const pager = ensureProjectDetailPager(dialog);
    if (!pager) {
      return;
    }

    const currentIndex = state.projectDetailPagerContext.currentIndex;
    const total = state.projectDetailPagerContext.items.length;
    const previousButton = pager.querySelector(
      '[data-legacy-project-pager-direction="previous"]',
    );
    const nextButton = pager.querySelector(
      '[data-legacy-project-pager-direction="next"]',
    );
    const status = pager.querySelector('.legacy-project-pager__status');
    const isLoading = dialog.dataset.legacyProjectPagerLoading === 'true';

    previousButton.disabled = isLoading || currentIndex <= 0;
    nextButton.disabled = isLoading || currentIndex >= total - 1;
    status.textContent = `${currentIndex + 1} / ${total}`;
    dialog.dataset.legacyProjectPagerCurrentIndex = String(currentIndex);
  };

  /**
   * Finds a preloaded or currently-preloading iframe for an item.
   */
  const getProjectDetailPreloadIframe = (dialog, item, loadedOnly = false) => (
    Array.from(
      dialog.querySelectorAll('iframe.legacy-project-iframe--preload'),
    ).find((iframe) => (
      iframe.dataset.legacyProjectId === item.projectId &&
      (!loadedOnly || iframe.dataset.legacyProjectPreloadReady === 'true')
    )) || null
  );

  /**
   * Marks the project content area as busy while the next iframe is preloading.
   */
  const setProjectDetailPagerLoading = (dialog, loading, token = null) => {
    const iframeWrapper = dialog.querySelector('.legacy-project-iframe-wrapper');
    if (loading) {
      dialog.dataset.legacyProjectPagerLoading = 'true';
      if (token) {
        dialog.dataset.legacyProjectPagerLoadToken = token;
      }
      iframeWrapper?.setAttribute('aria-busy', 'true');
      refreshProjectDetailPager(dialog);
      return;
    }

    delete dialog.dataset.legacyProjectPagerLoading;
    delete dialog.dataset.legacyProjectPagerLoadToken;
    iframeWrapper?.removeAttribute('aria-busy');
    refreshProjectDetailPager(dialog);
  };

  /**
   * Resets iframe-specific state before moving it to another project document.
   */
  const resetProjectDetailIframeState = (iframe) => {
    delete iframe.dataset.legacyProjectContentHeight;
    delete iframe.dataset.legacyProjectPrintHeight;
    delete iframe.dataset.legacyProjectPrintScrolling;
    LegacyProject.removeProjectModalPrintSource?.();
  };

  /**
   * Builds a hidden iframe without the active iframe class.
   */
  const createProjectDetailHiddenIframe = (iframe, item, className) => {
    const hiddenIframe = iframe.cloneNode(false);
    hiddenIframe.removeAttribute('src');
    hiddenIframe.removeAttribute('data-once');
    hiddenIframe.className = className;
    hiddenIframe.dataset.legacyProjectId = item.projectId;
    hiddenIframe.setAttribute('aria-hidden', 'true');
    hiddenIframe.setAttribute('tabindex', '-1');
    hiddenIframe.setAttribute('title', item.iframeTitle);
    hiddenIframe.setAttribute('loading', 'eager');
    delete hiddenIframe.dataset.legacyProjectContentHeight;
    delete hiddenIframe.dataset.legacyProjectPrintHeight;
    delete hiddenIframe.dataset.legacyProjectPrintScrolling;

    return hiddenIframe;
  };

  /**
   * Builds a hidden staging iframe that can become the visible project.
   */
  const createProjectDetailStagingIframe = (iframe, item, token) => {
    const stagingIframe = createProjectDetailHiddenIframe(
      iframe,
      item,
      'legacy-project-iframe--staging',
    );
    stagingIframe.dataset.legacyProjectPagerLoadToken = token;

    return stagingIframe;
  };

  /**
   * Builds a hidden sibling preload iframe and marks it ready after load.
   */
  const createProjectDetailPreloadIframe = (iframe, item) => {
    const preloadIframe = createProjectDetailHiddenIframe(
      iframe,
      item,
      'legacy-project-iframe--preload',
    );
    preloadIframe.addEventListener('load', () => {
      preloadIframe.dataset.legacyProjectPreloadReady = 'true';
    }, { once: true });
    preloadIframe.addEventListener('error', () => {
      preloadIframe.remove();
    }, { once: true });

    return preloadIframe;
  };

  /**
   * Starts warming the immediate previous and next project iframes.
   */
  const preloadProjectDetailSiblingIframes = (dialog) => {
    if (
      !state.projectDetailPagerContext ||
      dialog.dataset.legacyProjectPagerLoading === 'true'
    ) {
      return;
    }

    const iframe = dialog.querySelector('iframe.legacy-project-iframe');
    const iframeWrapper = iframe?.closest('.legacy-project-iframe-wrapper');
    if (!iframe || !iframeWrapper) {
      return;
    }

    const scheduledContext = state.projectDetailPagerContext;
    const scheduledIndex = scheduledContext.currentIndex;
    const items = scheduledContext.items;
    const preloadItems = [];
    for (let offset = 1; offset <= constants.projectDetailPagerPreloadRadius; offset++) {
      [scheduledIndex - offset, scheduledIndex + offset].forEach((index) => {
        if (hasProjectDetailPagerIndex(index)) {
          preloadItems.push(items[index]);
        }
      });
    }

    const keepProjectIds = new Set(
      preloadItems.map((item) => item.projectId),
    );
    removeProjectDetailPreloadIframes(dialog, keepProjectIds);

    const warm = () => {
      if (
        !dialog.isConnected ||
        dialog.dataset.legacyProjectPagerLoading === 'true' ||
        state.projectDetailPagerContext !== scheduledContext ||
        state.projectDetailPagerContext.currentIndex !== scheduledIndex
      ) {
        return;
      }
      preloadItems.forEach((item) => {
        if (!getProjectDetailPreloadIframe(dialog, item)) {
          const preloadIframe = createProjectDetailPreloadIframe(iframe, item);
          iframeWrapper.appendChild(preloadIframe);
          preloadIframe.src = item.iframeSrc;
        }
      });
    };

    if (window.requestIdleCallback) {
      window.requestIdleCallback(warm, { timeout: 500 });
    }
    else {
      setTimeout(warm, 0);
    }
  };

  /**
   * Makes the fully loaded staging iframe the visible project document.
   */
  const completeProjectDetailIframeSwap = (dialog, stagingIframe, item, index) => {
    if (
      !dialog.isConnected ||
      !state.projectDetailPagerContext ||
      dialog.dataset.legacyProjectPagerLoadToken !==
        stagingIframe.dataset.legacyProjectPagerLoadToken
    ) {
      stagingIframe.remove();
      return;
    }

    const iframe = dialog.querySelector('iframe.legacy-project-iframe');
    const title = dialog.querySelector('.ui-dialog-title');
    if (!iframe || !title) {
      stagingIframe.remove();
      setProjectDetailPagerLoading(dialog, false);
      return;
    }

    state.projectDetailPagerContext.currentIndex = index;
    title.textContent = item.title;
    stagingIframe.className = iframe.className;
    stagingIframe.removeAttribute('aria-hidden');
    stagingIframe.removeAttribute('tabindex');
    delete stagingIframe.dataset.legacyProjectId;
    delete stagingIframe.dataset.legacyProjectPagerLoadToken;
    delete stagingIframe.dataset.legacyProjectPreloadReady;
    resetProjectDetailIframeState(stagingIframe);
    iframe.remove();
    removeProjectDetailStagingIframes(dialog, stagingIframe);
    dialog.querySelector('.ui-dialog-content')?.scrollTo(0, 0);
    setProjectDetailPagerLoading(dialog, false);
    attachProjectDetailPagerIframeInput(dialog);
    Drupal.attachBehaviors(dialog);
    preloadProjectDetailSiblingIframes(dialog);
  };

  /**
   * Restores pager controls if the hidden iframe fails to load.
   */
  const failProjectDetailIframeSwap = (dialog, stagingIframe) => {
    if (
      dialog.dataset.legacyProjectPagerLoadToken ===
      stagingIframe.dataset.legacyProjectPagerLoadToken
    ) {
      setProjectDetailPagerLoading(dialog, false);
    }
    stagingIframe.remove();
  };

  /**
   * Loads a project from the captured pager context into the existing dialog.
   */
  const goToProjectDetailIndex = (dialog, index) => {
    if (
      dialog.dataset.legacyProjectPagerLoading === 'true' ||
      !hasProjectDetailPagerIndex(index) ||
      index === state.projectDetailPagerContext.currentIndex
    ) {
      return false;
    }

    const item = state.projectDetailPagerContext.items[index];
    const iframe = dialog.querySelector('iframe.legacy-project-iframe');
    const title = dialog.querySelector('.ui-dialog-title');
    const iframeWrapper = iframe?.closest('.legacy-project-iframe-wrapper');
    if (!iframe || !title || !iframeWrapper) {
      return false;
    }

    const preloadedIframe = getProjectDetailPreloadIframe(dialog, item, true);
    if (preloadedIframe) {
      const loadToken = `${Date.now()}-${Math.random()}`;
      dialog.dataset.legacyProjectPagerLoadToken = loadToken;
      preloadedIframe.dataset.legacyProjectPagerLoadToken = loadToken;
      preloadedIframe.className = 'legacy-project-iframe--staging';
      completeProjectDetailIframeSwap(dialog, preloadedIframe, item, index);
      return true;
    }

    const loadToken = `${Date.now()}-${Math.random()}`;
    setProjectDetailPagerLoading(dialog, true, loadToken);

    let stagingIframe = getProjectDetailPreloadIframe(dialog, item);
    if (stagingIframe) {
      stagingIframe.className = 'legacy-project-iframe--staging';
      delete stagingIframe.dataset.legacyProjectPreloadReady;
      stagingIframe.dataset.legacyProjectPagerLoadToken = loadToken;
    }
    else {
      removeProjectDetailStagingIframes(dialog);
      stagingIframe = createProjectDetailStagingIframe(
        iframe,
        item,
        loadToken,
      );
    }
    stagingIframe.addEventListener('load', () => {
      completeProjectDetailIframeSwap(dialog, stagingIframe, item, index);
    }, { once: true });
    stagingIframe.addEventListener('error', () => {
      failProjectDetailIframeSwap(dialog, stagingIframe);
    }, { once: true });
    if (!stagingIframe.parentElement) {
      stagingIframe.src = item.iframeSrc;
      iframeWrapper.appendChild(stagingIframe);
    }

    return true;
  };

  /**
   * Pages relative to the currently loaded project.
   */
  const goToRelativeProjectDetail = (dialog, delta) => {
    if (!state.projectDetailPagerContext) {
      return false;
    }
    return goToProjectDetailIndex(
      dialog,
      state.projectDetailPagerContext.currentIndex + delta,
    );
  };

  /**
   * Avoids turning text-editing arrow keys into pager navigation.
   */
  const isProjectDetailPagerTextInput = (target) => (
    target?.closest?.('input, textarea, select, [contenteditable="true"]')
  );

  /**
   * Handles keyboard paging for the modal shell and the iframe document.
   */
  const handleProjectDetailPagerKeydown = (dialog, event) => {
    if (
      event.defaultPrevented ||
      event.altKey ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      isProjectDetailPagerTextInput(event.target)
    ) {
      return;
    }

    if (event.key === 'ArrowLeft') {
      if (goToRelativeProjectDetail(dialog, -1)) {
        event.preventDefault();
      }
    }
    else if (event.key === 'ArrowRight') {
      if (goToRelativeProjectDetail(dialog, 1)) {
        event.preventDefault();
      }
    }
  };

  /**
   * Handles horizontal wheel or trackpad gestures as project paging.
   */
  const handleProjectDetailPagerWheel = (dialog, event) => {
    if (
      state.projectDetailPagerWheelLocked ||
      Math.abs(event.deltaX) < constants.projectDetailPagerGestureThreshold ||
      Math.abs(event.deltaX) <= Math.abs(event.deltaY)
    ) {
      return;
    }

    event.preventDefault();
    if (goToRelativeProjectDetail(dialog, event.deltaX > 0 ? 1 : -1)) {
      state.projectDetailPagerWheelLocked = true;
      setTimeout(() => {
        state.projectDetailPagerWheelLocked = false;
      }, 500);
    }
  };

  /**
   * Handles touch swipes as project paging.
   */
  const attachProjectDetailPagerTouch = (target, dialog) => {
    let startX = 0;
    let startY = 0;

    target.addEventListener('touchstart', (event) => {
      if (event.touches.length !== 1) {
        return;
      }
      startX = event.touches[0].clientX;
      startY = event.touches[0].clientY;
    }, { passive: true });

    target.addEventListener('touchend', (event) => {
      if (!startX || event.changedTouches.length !== 1) {
        return;
      }

      const deltaX = event.changedTouches[0].clientX - startX;
      const deltaY = event.changedTouches[0].clientY - startY;
      startX = 0;
      startY = 0;

      if (
        Math.abs(deltaX) < constants.projectDetailPagerGestureThreshold ||
        Math.abs(deltaX) <= Math.abs(deltaY)
      ) {
        return;
      }

      goToRelativeProjectDetail(dialog, deltaX < 0 ? 1 : -1);
    }, { passive: true });
  };

  /**
   * Adds keyboard, wheel, and swipe handlers to one event target.
   */
  const attachProjectDetailPagerInput = (target, dialog) => {
    target.addEventListener('keydown', (event) => {
      handleProjectDetailPagerKeydown(dialog, event);
    });
    target.addEventListener('wheel', (event) => {
      handleProjectDetailPagerWheel(dialog, event);
    }, { passive: false });
    attachProjectDetailPagerTouch(target, dialog);
  };

  /**
   * Adds pager input handlers inside the same-origin project iframe.
   */
  const attachProjectDetailPagerIframeInput = (dialog) => {
    const iframe = dialog.querySelector('iframe.legacy-project-iframe');
    if (!iframe) {
      return;
    }

    try {
      const doc = LegacyProject.getIframeDocument(iframe);
      if (
        !doc?.documentElement ||
        doc.documentElement.dataset.legacyProjectPagerInputAttached
      ) {
        return;
      }
      doc.documentElement.dataset.legacyProjectPagerInputAttached = 'true';
      attachProjectDetailPagerInput(doc, dialog);
    }
    catch (e) {
      // Paging controls in the parent modal remain usable if frame access fails.
    }
  };

  /**
   * Initializes the detail modal pager for the captured project-count context.
   */
  LegacyProject.attachProjectDetailPager = (element) => {
    const dialog = LegacyProject.getDialogWidget(element);
    if (!dialog || !dialog.classList.contains('project-detail-modal')) {
      return;
    }

    if (
      !state.projectDetailPagerContext ||
      state.projectDetailPagerContext.items.length < 2
    ) {
      removeProjectDetailPager(dialog);
      return;
    }

    if (!dialog.dataset.legacyProjectPagerInputAttached) {
      dialog.dataset.legacyProjectPagerInputAttached = 'true';
      attachProjectDetailPagerInput(dialog, dialog);
      dialog.addEventListener('click', (event) => {
        const button = event.target.closest?.(
          '[data-legacy-project-pager-direction]',
        );
        if (!button || button.disabled) {
          return;
        }
        goToRelativeProjectDetail(
          dialog,
          button.dataset.legacyProjectPagerDirection === 'next' ? 1 : -1,
        );
      });
    }

    const iframe = dialog.querySelector('iframe.legacy-project-iframe');
    iframe?.addEventListener('load', () => {
      attachProjectDetailPagerIframeInput(dialog);
    });
    attachProjectDetailPagerIframeInput(dialog);
    refreshProjectDetailPager(dialog);
    preloadProjectDetailSiblingIframes(dialog);
  };

  /**
   * Initializes pagers for detail dialogs that opened before this behavior ran.
   */
  LegacyProject.attachOpenProjectDetailPagers = () => {
    document.querySelectorAll('.project-detail-modal.ui-dialog').forEach((dialog) => {
      LegacyProject.attachProjectDetailPager(dialog);
    });
  };

})(Drupal);
